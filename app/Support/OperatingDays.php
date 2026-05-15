<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for "was the clinic operating on date X?"
 *
 * Three layered inputs:
 *   1. settings.business_working_days  — weekly pattern (default Mon-Sat / Sun off)
 *   2. working_day_exceptions          — per-date overrides (is_working = 0 closed, = 1 forced open)
 *   3. business_closures               — date-range events, global or location-scoped
 *
 * Precedence: a date is closed if either non-working OR covered by a closure
 * that hits ALL of the caller's selected branches. A per-date exception can
 * flip a default-off day to open, but a closure that covers it still wins
 * (the branch is shut regardless of what the weekly pattern says).
 *
 * Callers:
 *   - InvoiceGenerationService — operating-days denominator
 *   - Dashboard\Metrics\UtilizationMetric — closed-by-branch + non-working maps
 */
final class OperatingDays
{
    /**
     * Operating dates (YYYY-MM-DD) in the window for the given branch set.
     *
     * - Empty $locationIds means org-wide rollup: only GLOBAL closures
     *   subtract a day (a closure scoped to one branch doesn't pull the
     *   org-level number down — the other branches were still open).
     * - Non-empty $locationIds: a date is operating if at least one of the
     *   selected branches was open that day. A branch-specific closure
     *   subtracts only when every selected branch is hit by a closure.
     *
     * @param  list<int>  $locationIds
     * @return list<string>
     */
    public static function datesInRange(
        int $accountId,
        array $locationIds,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): array {
        $startStr = $start->format('Y-m-d');
        $endStr = $end->format('Y-m-d');

        $nonWorking = self::nonWorkingDates($accountId, $startStr, $endStr);
        $closedByBranch = self::closedBranchDates($accountId, $startStr, $endStr);

        $out = [];
        $cursor = $start->startOfDay();
        $endDay = $end->startOfDay();

        while ($cursor->lte($endDay)) {
            $date = $cursor->format('Y-m-d');
            $cursor = $cursor->addDay();

            if (isset($nonWorking[$date])) {
                continue;
            }
            if (isset($closedByBranch[0][$date])) {
                continue;
            }
            if ($locationIds !== []) {
                $allClosed = true;
                foreach ($locationIds as $lid) {
                    if (! isset($closedByBranch[(int) $lid][$date])) {
                        $allClosed = false;
                        break;
                    }
                }
                if ($allClosed) {
                    continue;
                }
            }

            $out[] = $date;
        }

        return $out;
    }

    /**
     * Non-working dates [YYYY-MM-DD => true] from weekly pattern + per-date
     * exceptions. Excludes business_closures — those are layered separately
     * via closedBranchDates() because they carry per-branch granularity.
     *
     * @return array<string, true>
     */
    public static function nonWorkingDates(int $accountId, string $start, string $end): array
    {
        $working = self::weeklyPattern($accountId);

        // ORDER BY id ASC so `pluck` produces deterministic "highest id
        // wins" semantics — there's no DB unique constraint on
        // (account_id, exception_date), so dirty data is possible. The
        // larger id is the more recently inserted row.
        $exceptions = DB::table('working_day_exceptions')
            ->where('account_id', $accountId)
            ->whereBetween('exception_date', [$start, $end])
            ->orderBy('id')
            ->pluck('is_working', 'exception_date')
            ->toArray();

        $dayNames = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
        $out = [];
        $cursor = CarbonImmutable::parse($start)->startOfDay();
        $endDay = CarbonImmutable::parse($end)->startOfDay();

        while ($cursor->lte($endDay)) {
            $date = $cursor->format('Y-m-d');
            if (array_key_exists($date, $exceptions)) {
                if ((int) $exceptions[$date] === 0) {
                    $out[$date] = true;
                }
            } else {
                $dayName = $dayNames[(int) $cursor->dayOfWeek];
                if (empty($working[$dayName])) {
                    $out[$date] = true;
                }
            }
            $cursor = $cursor->addDay();
        }

        return $out;
    }

    /**
     * Branch-keyed closure map: [branch_id => [YYYY-MM-DD => true]]. The
     * special key 0 holds GLOBAL closures (no location pivot rows, or all
     * pivot rows are "All Centres"-style pseudo-locations).
     *
     * @return array<int, array<string, true>>
     */
    public static function closedBranchDates(int $accountId, string $start, string $end): array
    {
        $rows = DB::table('business_closures as bc')
            ->leftJoin('business_closure_locations as bcl', 'bcl.business_closure_id', '=', 'bc.id')
            ->leftJoin('locations as l', 'l.id', '=', 'bcl.location_id')
            ->where('bc.account_id', $accountId)
            ->whereNull('bc.deleted_at')
            ->where('bc.start_date', '<=', $end)
            ->where('bc.end_date', '>=', $start)
            ->select([
                'bc.id',
                'bc.start_date',
                'bc.end_date',
                'bcl.location_id',
                'l.name as location_name',
            ])
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $byClosure = [];
        foreach ($rows as $row) {
            $byClosure[$row->id]['start'] = (string) $row->start_date;
            $byClosure[$row->id]['end'] = (string) $row->end_date;
            if ($row->location_id !== null) {
                $byClosure[$row->id]['locations'][] = [
                    'id' => (int) $row->location_id,
                    'name' => (string) ($row->location_name ?? ''),
                ];
            }
        }

        $map = [];
        $from = CarbonImmutable::parse($start)->startOfDay();
        $to = CarbonImmutable::parse($end)->startOfDay();

        foreach ($byClosure as $closure) {
            $cStart = CarbonImmutable::parse($closure['start'])->startOfDay();
            $cEnd = CarbonImmutable::parse($closure['end'])->startOfDay();
            $iterStart = $cStart->lt($from) ? $from : $cStart;
            $iterEnd = $cEnd->gt($to) ? $to : $cEnd;
            if ($iterStart->gt($iterEnd)) {
                continue;
            }

            $locations = $closure['locations'] ?? [];
            $isGlobal = $locations === [] || self::allPseudo($locations);
            $branchIds = $isGlobal
                ? [0]
                : array_values(array_unique(array_map(
                    static fn (array $l): int => $l['id'],
                    $locations,
                )));

            for ($d = $iterStart; $d->lte($iterEnd); $d = $d->addDay()) {
                $date = $d->format('Y-m-d');
                foreach ($branchIds as $branchId) {
                    $map[$branchId][$date] = true;
                }
            }
        }

        return $map;
    }

    /**
     * @return array<string, bool>
     */
    private static function weeklyPattern(int $accountId): array
    {
        $defaults = [
            'monday' => true,
            'tuesday' => true,
            'wednesday' => true,
            'thursday' => true,
            'friday' => true,
            'saturday' => true,
            'sunday' => false,
        ];

        // ORDER BY id DESC → take the most recently inserted row when
        // dirty data has two settings rows for the same slug. value()
        // returns the first row in the ordering, so "latest wins."
        $json = DB::table('settings')
            ->where('account_id', $accountId)
            ->where('slug', 'business_working_days')
            ->orderByDesc('id')
            ->value('data');

        if (is_string($json) && $json !== '') {
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                return array_merge($defaults, $decoded);
            }
        }

        return $defaults;
    }

    /**
     * Monotonic version counter per account. Bumped whenever a closure,
     * working-day exception, or weekly-pattern setting changes for the
     * account. Callers that cache derived results (e.g. BenchmarkCalculator's
     * per-doctor revenue pool) embed this in their cache key so a holiday
     * added at 9:31am invalidates the 8:00am-cached benchmark immediately
     * instead of waiting for the natural 1-hour expiry.
     *
     * Backed by Cache::increment so it survives the request lifecycle
     * without needing Redis (file cache supports atomic increment).
     */
    public static function version(int $accountId): int
    {
        return (int) Cache::get(self::versionKey($accountId), 0);
    }

    /**
     * Bump the per-account version counter. Called from observers on
     * BusinessClosure, WorkingDayException, and from the controller that
     * updates the `business_working_days` setting. Idempotent w.r.t.
     * caches keyed on the returned value — repeated bumps just keep moving
     * the cursor forward.
     */
    public static function bumpVersion(int $accountId): int
    {
        $key = self::versionKey($accountId);
        // Cache::increment is atomic on the file driver (uses flock under
        // the hood) and on every other supported driver. Seed at 0 if the
        // key doesn't exist yet so increment can land on 1.
        if (! Cache::has($key)) {
            Cache::forever($key, 0);
        }
        return (int) Cache::increment($key);
    }

    private static function versionKey(int $accountId): string
    {
        return "operating_days_version:{$accountId}";
    }

    /**
     * @param  list<array{id: int, name: string}>  $locations
     */
    private static function allPseudo(array $locations): bool
    {
        if ($locations === []) {
            return false;
        }
        foreach ($locations as $loc) {
            if (! str_starts_with($loc['name'], 'All ')) {
                return false;
            }
        }

        return true;
    }
}
