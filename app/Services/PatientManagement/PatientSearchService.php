<?php

declare(strict_types=1);

namespace App\Services\PatientManagement;

use App\Helpers\GeneralFunctions;
use App\Models\Appointments;
use App\Models\Leads;
use App\Models\Patients;
use Config;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PatientSearchService
{
    public static function contactStatus(string|null $contact): string
    {
        if (!Gate::allows('contact')) {
            return '***********';
        }

        return $contact ?? '';
    }

    public static function patientSearch(string|int $id): string|int
    {
        if (is_numeric($id)) {
            return $id;
        }

        // Strip a leading `C-` / `c-` / `P-` / `p-` prefix and return the
        // bare numeric id. Earlier this only handled uppercase `C-`, so
        // a lowercase `c-34` passed straight through to the SQL filter
        // and 500'd the request. Case-insensitive match also covers the
        // legacy `P-` prefix that addPatientFilter used to special-case.
        if (preg_match('/^[CcPp]-(\d+)$/', $id, $m)) {
            return $m[1];
        }

        return $id;
    }

    public static function patientSearchStringAdd(string|int $id): string
    {
        if (is_numeric($id)) {
            return 'C-' . $id;
        }

        return (string) $id;
    }

    /**
     * Decide which lookup bucket a free-text search query falls into.
     * Single source of truth for every patient-search call site (Plans
     * datatable, picker typeahead, invoices typeahead, etc.). The frontend
     * mirrors this exact heuristic in `src/lib/highlight.tsx::classifySearch`
     * so cell highlighting agrees with which column actually matched.
     *
     * Returns one of:
     *   { type: 'patient_code', digits: '<id>' }     — `C-<id>` / `P-<id>`
     *   { type: 'phone',        digits: '<digits>' } — phone-shaped (≥7 digits
     *                                                  after stripping format
     *                                                  chars; original input
     *                                                  contains no letters)
     *   { type: 'short_id',     digits: '<digits>' } — pure 1–6 digit numeric
     *   { type: 'text' }                             — anything else
     *
     * Stripping `+ - ( ) . space` before length-checking lets `+923212423100`,
     * `(0321) 242 3100`, and `0321-2423100` all route to the phone branch.
     *
     * @return array{type: 'patient_code'|'phone'|'short_id'|'text', digits?: string}
     */
    public static function classifySearchInput(string $q): array
    {
        if (preg_match('/^[CcPp]-(\d+)$/', $q, $m)) {
            return ['type' => 'patient_code', 'digits' => $m[1]];
        }

        $digits = preg_replace('/\D+/', '', $q) ?? '';

        if ($digits !== '' && preg_match('/^[\d\s+\-().]+$/', $q) === 1) {
            // Original was numeric + format characters only.
            //
            // Heuristic: a leading `0` is a phone tell — neither
            // package_id nor patient_id ever has a leading zero, but
            // every Pakistani national-prefix phone does. Treat any
            // 0-prefixed digit string as a phone, even at 3–6 chars
            // (the operator is filling in a partial number).
            //
            // Same for `+` or `92` prefixes: clearly phone-shaped.
            if (str_starts_with($digits, '0') || str_starts_with($q, '+') || str_starts_with($digits, '92')) {
                return ['type' => 'phone', 'digits' => $digits];
            }

            if (strlen($digits) >= 7) {
                return ['type' => 'phone', 'digits' => $digits];
            }
            if (strlen($digits) >= 1 && strlen($digits) <= 6) {
                return ['type' => 'short_id', 'digits' => $digits];
            }
        }

        return ['type' => 'text'];
    }

    /**
     * Apply a unified patient search to any query builder. Single entry
     * point for every datatable that has a "search by patient" box —
     * Plans, Patients, Treatments, Consultations, Vouchers, etc. —
     * keeping the routing identical across surfaces.
     *
     * The classifier sends:
     *   - `C-<id>` / `P-<id>` / 1–6 digits → exact `$idColumn = X`
     *   - phone-shaped (≥7 digits, leading 0/+/92) → `$idColumn IN (resolverIds)`
     *   - anything else → FT-name `$idColumn IN (resolverIds)`
     *
     * If the resolver returns no candidates, a `0 = 1` clause is pushed
     * so the outer query short-circuits to zero rows (rather than ignoring
     * the search and returning everything).
     *
     * `$idColumn` is the column the outer query joins to — `users.id` for
     * a Patients-table query, `packages.patient_id` for Plans, etc.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $query
     */
    public static function applyPatientFilter($query, string $value, string $idColumn, ?int $accountId = null): void
    {
        $value = trim($value);
        if ($value === '') {
            return;
        }

        $shape = self::classifySearchInput($value);

        if ($shape['type'] === 'patient_code' || $shape['type'] === 'short_id') {
            $query->where($idColumn, (int) $shape['digits']);
            return;
        }

        $candidateIds = $shape['type'] === 'phone'
            ? self::resolvePatientIdsByPhone($shape['digits'], $accountId)
            : self::resolvePatientIdsByText($value, $accountId);

        if ($candidateIds === []) {
            $query->whereRaw('0 = 1');
            return;
        }

        $query->whereIn($idColumn, $candidateIds);
    }

    /**
     * Phone lookup that canonicalises the query first. Pakistani numbers
     * land in the DB as a bare 10-digit mobile (`3xxxxxxxxx`) — leading
     * `0` and country-code `92` are stripped on save. The user might
     * type the same number any of these ways:
     *   - `3212423100`        (canonical)
     *   - `03212423100`       (with national leading 0)
     *   - `923212423100`      (with country code, no plus)
     *   - `+923212423100`     (full international)
     *   - `0923212423100`     (international with extra 0)
     * Reduce all of those to the canonical form before the prefix
     * LIKE so the indexed `phone_normalized` column hits.
     *
     * Returns up to 200 patient ids — callers apply their own LIMIT and
     * scope / active filters in the outer query.
     *
     * Pass `$accountId` to scope the candidate set to one tenant — this
     * is purely a tightening optimisation (the outer query still applies
     * its own account filter for defence-in-depth). Plans datatable can
     * also pass it because users and their packages share the same
     * account_id, so pre-filtering at the user layer can only shrink the
     * id set, never miss matches.
     *
     * @return list<int>
     */
    public static function resolvePatientIdsByPhone(string $rawDigits, ?int $accountId = null): array
    {
        $candidates = self::phonePrefixCandidates($rawDigits);
        if ($candidates === []) {
            return [];
        }

        $query = DB::table('users')
            ->where('user_type_id', 3)
            ->where(function ($q) use ($candidates): void {
                foreach ($candidates as $c) {
                    $q->orWhere('phone_normalized', 'LIKE', $c.'%');
                }
            });

        if ($accountId !== null) {
            $query->where('account_id', $accountId);
        }

        return $query->limit(200)
            ->pluck('id')
            ->map(fn ($v): int => (int) $v)
            ->all();
    }

    /**
     * Token-aware name lookup. Splits `$text` into tokens, builds a
     * MATCH AGAINST ... IN BOOLEAN MODE expression with `+token*` per
     * token (AND across tokens, prefix expansion), and falls back to
     * a prefix LIKE on `users.name` when every token is shorter than
     * MariaDB's default `innodb_ft_min_token_size` (3) — otherwise the
     * FT engine quietly returns nothing for short queries like "ab".
     *
     * Returns up to 200 patient ids — callers apply their own LIMIT and
     * scope / active filters in the outer query.
     *
     * Pass `$accountId` to scope the candidate set to one tenant. Same
     * reasoning as `resolvePatientIdsByPhone`.
     *
     * @return list<int>
     */
    public static function resolvePatientIdsByText(string $text, ?int $accountId = null): array
    {
        $tokens = preg_split('/\s+/', trim($text)) ?: [];
        $tokens = array_values(array_filter($tokens, fn ($t) => $t !== ''));
        if ($tokens === []) {
            return [];
        }

        // FT minimum token size (InnoDB default = 3). Anything shorter
        // gets ignored by the FULLTEXT scan — fall back to LIKE prefix
        // on the longest short token so we still return something useful.
        $longEnough = array_filter($tokens, fn ($t) => strlen($t) >= 3);

        if ($longEnough === []) {
            // Escape SQL LIKE metacharacters in user-supplied input. PDO
            // parameter binding prevents SQL injection but does NOT escape
            // `%` / `_` inside the bound LIKE pattern.
            $token = addcslashes($tokens[0], '\\%_');

            $query = DB::table('users')
                ->where('user_type_id', 3)
                ->where('name', 'LIKE', $token.'%');

            if ($accountId !== null) {
                $query->where('account_id', $accountId);
            }

            return $query->orderBy('name')
                ->limit(200)
                ->pluck('id')
                ->map(fn ($v): int => (int) $v)
                ->all();
        }

        // Boolean-mode expression: each token sanitised + `+...*` so it
        // must appear AND can match as a prefix. Strip out any chars
        // that are themselves Boolean-mode operators (+, -, ", *, (, ),
        // ~, <, >, @) to avoid syntax errors and abuse.
        $sanitised = array_map(
            fn ($t) => '+'.preg_replace('/[+\-"*()<>~@]/', '', $t).'*',
            $longEnough,
        );
        $expression = implode(' ', $sanitised);

        $query = DB::table('users')
            ->where('user_type_id', 3)
            ->whereRaw('MATCH(name) AGAINST (? IN BOOLEAN MODE)', [$expression]);

        if ($accountId !== null) {
            $query->where('account_id', $accountId);
        }

        return $query
            ->orderByRaw('MATCH(name) AGAINST (? IN BOOLEAN MODE) DESC', [$expression])
            ->limit(200)
            ->pluck('id')
            ->map(fn ($v): int => (int) $v)
            ->all();
    }

    /**
     * Build the set of prefix candidates to LIKE-match against the canonical
     * `phone_normalized` column. Operators type Pakistani phones a dozen
     * different ways; we'd rather match more than less. The DB form is
     * always the bare 10-digit `3xxxxxxxxx`. Candidate set:
     *
     *   - the digits as typed
     *   - drop a leading `0`           (national prefix variant)
     *   - drop a leading `92`          (country code without `+`)
     *   - drop a leading `092`         (country code with extra national 0)
     *   - drop a leading `0092`        (international dial prefix)
     *
     * Returned candidates are deduped and trimmed to ≥ 3 chars to keep the
     * prefix scan tight (a 1-digit prefix would match every phone in the
     * table). MariaDB OR's the prefix matches over the same B-tree index.
     *
     * @return list<string>
     */
    private static function phonePrefixCandidates(string $digits): array
    {
        $set = [$digits];
        if (str_starts_with($digits, '0092')) {
            $set[] = substr($digits, 4);
        }
        if (str_starts_with($digits, '092')) {
            $set[] = substr($digits, 3);
        }
        if (str_starts_with($digits, '92')) {
            $set[] = substr($digits, 2);
        }
        if (str_starts_with($digits, '0')) {
            $set[] = substr($digits, 1);
        }

        return array_values(array_unique(array_filter(
            $set,
            fn ($c) => $c !== '' && strlen($c) >= 3,
        )));
    }

    public static function patientNameUpdate(string $phone, string $name): void
    {
        $accountId = Auth::user()->account_id;
        $patient_phone = GeneralFunctions::cleanNumber($phone);
        Leads::where(['phone' => $patient_phone])->update([
            'name' => $name,
        ]);

        Patients::where([
            'phone' => $patient_phone,
            'user_type_id' => Config::get('constants.patient_id'),
            'account_id' => $accountId,
        ])->update(['name' => $name]);

        Appointments::whereIn('patient_id', function ($query) use ($patient_phone, $accountId) {
            $query->select('id')
                ->from('users')
                ->where([
                    'phone' => $patient_phone,
                    'user_type_id' => Config::get('constants.patient_id'),
                    'account_id' => $accountId,
                ]);
        })->update(['name' => $name]);
    }
}
