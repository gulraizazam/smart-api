<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\DecryptLegacyOnRead;
use App\Helpers\GeneralFunctions;
use App\Helpers\PatientAccessScope;
use App\Services\PatientManagement\PatientSearchService;
use App\Services\Phone\PhoneFormattingService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class Patients extends BaseModel
{
    use SoftDeletes;

    protected $table = 'users';

    protected static string $_table = 'users';

    protected static int $USER_TYPE = 3;

    protected static function booted(): void
    {
        static::addGlobalScope('patients_only', function (Builder $builder): void {
            $builder->where('users.user_type_id', self::$USER_TYPE);
        });

        // Keep `phone_normalized` in lockstep with `phone`, mirroring
        // the hook on `User::booted()` (User.php:125-131). The Patients
        // model shares the `users` table but extends BaseModel, not
        // User, so the User hook never fired on Patient creates — every
        // patient inserted via Patients::create() had a NULL
        // phone_normalized, silently breaking phone search (which
        // queries `WHERE phone_normalized LIKE '302%'`).
        static::saving(function (Patients $patient): void {
            if ($patient->isDirty('phone')) {
                $raw = (string) ($patient->phone ?? '');
                $digits = preg_replace('/\D+/', '', $raw) ?? '';
                $patient->phone_normalized = $digits === '' ? null : $digits;
            }
        });
    }

    protected static array $_fillable = [
        'name', 'email', 'phone', 'main_account', 'gender',
        'cnic', 'dob', 'referred_by', 'user_type_id',
    ];

    protected $fillable = [
        'name', 'email', 'password', 'remember_token', 'phone',
        'main_account', 'gender', 'cnic', 'dob',
        'referred_by', 'active', 'user_type_id', 'resource_type_id',
        'account_id', 'created_by', 'image_src',
    ];

    /**
     * Hidden from array/JSON serialization. The Patients model shares the
     * `users` table, so without this any path that returns a Patients
     * instance raw (e.g. the legacy users/get_patient_number lookup)
     * serializes the bcrypt password hash + remember_token to the client.
     * Mirrors User::$hidden. Security audit 2026-06.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'dob' => 'date',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            // Plaintext (encryption removed 2026-06-04). See User::casts().
            'cnic' => DecryptLegacyOnRead::class,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors & Mutators
    |--------------------------------------------------------------------------
    */

    protected function patientCode(): Attribute
    {
        return Attribute::make(
            get: fn (): string => "C-{$this->id}",
        );
    }

    /**
     * Profile-image URL, or null when the patient has no image. The
     * fallback used to be `asset('images/default-avatar.png')` but the
     * file does not exist anywhere in `public/`; rendering it produced
     * a broken-image. The SPA's patient types declare `image_url` as
     * nullable and the UI shows initials-based avatars when null.
     */
    protected function profileImageUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->image_src
                ? route('admin.files.patient_image_api', ['filename' => $this->image_src])
                : null,
        );
    }

    protected function formattedPhone(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->phone ? GeneralFunctions::prepareNumber4Call($this->phone) : null,
        );
    }

    protected function isActive(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => (bool) $this->active,
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function leads(): HasMany
    {
        return $this->hasMany(Leads::class, 'patient_id');
    }

    public function membership(): HasOne
    {
        return $this->hasOne(Membership::class, 'patient_id')
            ->orderByDesc('active')
            ->orderByDesc('id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(PatientNote::class, 'patient_id');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointments::class, 'patient_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Documents::class, 'user_id');
    }

    public function packages(): HasMany
    {
        return $this->hasMany(Packages::class, 'patient_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoices::class, 'patient_id');
    }

    public function audit_field_before(): HasMany
    {
        return $this->hasMany(AuditTrailChanges::class, 'field_before');
    }

    public function audit_field_after(): HasMany
    {
        return $this->hasMany(AuditTrailChanges::class, 'field_after');
    }

    /*
    |--------------------------------------------------------------------------
    | Query Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeForAccount(Builder $query, int $accountId): Builder
    {
        return $query->where('account_id', $accountId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', 1);
    }

    public function scopePatientsOnly(Builder $query): Builder
    {
        return $query->where('user_type_id', self::$USER_TYPE);
    }

    public function scopeSearchByName(Builder $query, ?string $name): Builder
    {
        return $name ? $query->where('name', 'like', "%{$name}%") : $query;
    }

    public function scopeSearchByPhone(Builder $query, ?string $phone): Builder
    {
        if (! $phone) {
            return $query;
        }
        $cleanPhone = GeneralFunctions::cleanNumber($phone);

        return $query->where('phone', 'like', "%{$cleanPhone}%");
    }

    public function scopeSearchByEmail(Builder $query, ?string $email): Builder
    {
        return $email ? $query->where('email', 'like', "%{$email}%") : $query;
    }

    /*
    |--------------------------------------------------------------------------
    | Static Query Methods
    |--------------------------------------------------------------------------
    */

    public static function getAll(int $accountId): Collection
    {
        return self::patientsOnly()->active()->forAccount($accountId)->get();
    }

    public static function getActiveOnly(int|array|false $patientId = false): Collection
    {
        $query = self::patientsOnly()->active();

        if ($patientId) {
            $query->whereIn('id', (array) $patientId);
        }

        return $query->orderBy('name')->get();
    }

    public static function getPatientSearchOptimized(string $name, int $accountId): array
    {
        // Reject empty / single-character queries before they hit the search
        // branch. Without this, `?search=` produces wildcard scans that dump
        // the 10 most-recent patients with PII (phone, cnic, email, dob).
        // Cache is also bypassed for short input so attackers cannot bloat
        // it with random short strings.
        $name = trim($name);
        if (strlen($name) < 2) {
            return [];
        }

        $canViewContact = Gate::allows('patients.list.view_contact');

        [$scopeSql, $scopeBindings] = PatientAccessScope::rawClause('users.id');
        $cacheKey = "patient_search_{$accountId}_".PatientAccessScope::cacheSuffix().'_'.md5($name);

        $rows = Cache::remember($cacheKey, 300, function () use ($name, $accountId, $scopeSql, $scopeBindings): array {
            // Single classifier shared with PlanService::applyUnifiedSearch
            // and the SPA's `classifySearch` mirror. Picker semantics differ
            // from Plans only in the `short_id` branch: there's no plan-id
            // ambiguity here, so ≤ 6 digit input is treated as a patient id.
            $shape = PatientSearchService::classifySearchInput($name);

            if ($shape['type'] === 'patient_code' || $shape['type'] === 'short_id') {
                $rows = DB::select(
                    "SELECT name, id, phone, gender
                     FROM users
                     WHERE id = ? AND user_type_id = 3 AND active = 1 AND account_id = ?
                       {$scopeSql}
                     LIMIT 1",
                    array_merge([(int) $shape['digits'], $accountId], $scopeBindings)
                );

                return self::decryptCnicColumn($rows);
            }

            // phone / text branches — resolve to candidate patient ids
            // first, then apply scope/active filters in the outer query.
            // Resolver pre-filters by account_id so the 200-id headroom
            // isn't burned on other tenants; outer query keeps the
            // account check for defence-in-depth.
            $candidateIds = $shape['type'] === 'phone'
                ? PatientSearchService::resolvePatientIdsByPhone($shape['digits'], $accountId)
                : PatientSearchService::resolvePatientIdsByText($name, $accountId);

            if ($candidateIds === []) {
                return [];
            }

            // FIELD(id, ?, ?, ...) preserves the resolver's own ordering —
            // for the text branch that's MATCH() relevance, for phone it's
            // the prefix-match order. Without this MariaDB re-orders the IN
            // result by primary key, which loses relevance.
            $placeholders = implode(',', array_fill(0, count($candidateIds), '?'));

            $rows = DB::select(
                "SELECT name, id, phone, gender
                 FROM users
                 WHERE id IN ($placeholders)
                   AND user_type_id = 3 AND active = 1 AND account_id = ?
                   {$scopeSql}
                 ORDER BY FIELD(id, $placeholders)
                 LIMIT 10",
                array_merge(
                    $candidateIds,
                    [$accountId],
                    $scopeBindings,
                    $candidateIds,
                )
            );

            return self::decryptCnicColumn($rows);
        });

        // Strip phone per-request *after* cache retrieval so a consultant
        // (no `contact` permission) never sees phone numbers in the response,
        // even when the cache entry was populated by an admin. Phone-based
        // lookup is still allowed at the query layer so the consultant can
        // find a patient by typing their number — the property is removed
        // entirely (not masked) so callers receive no phone key at all.
        if (! $canViewContact) {
            foreach ($rows as $row) {
                unset($row->phone);
            }
        }

        return $rows;
    }

    /**
     * Decrypt the `cnic` field on raw DB::select rows.
     *
     * Raw queries bypass the Eloquent DecryptLegacyOnRead cast on this model,
     * so any path that selects `cnic` via DB::select must run results through
     * this helper to decrypt any leftover legacy ciphertext during the
     * rollout. Mirrors the cast's tolerance: if Crypt fails (plaintext row),
     * the raw value is returned unchanged.
     *
     * @param  array<int, \stdClass>  $rows
     * @return array<int, \stdClass>
     */
    private static function decryptCnicColumn(array $rows): array
    {
        foreach ($rows as $row) {
            if (! property_exists($row, 'cnic') || $row->cnic === null || $row->cnic === '') {
                continue;
            }
            try {
                $row->cnic = Crypt::decryptString((string) $row->cnic);
            } catch (DecryptException) {
                // Legacy plaintext row — leave as-is.
            }
        }

        return $rows;
    }

    public static function getByPhone(string $phone, int|false $accountId = false, int|false $patientId = false): ?self
    {
        // Match every equivalent stored form (canonical, raw leading zero,
        // 92/+92 country code) — the `phone` column is not normalised
        // consistently across the shared DB, so an exact match on the
        // cleaned number alone misses ~1k consultation-created rows that
        // kept the leading zero. See PhoneFormattingService::matchVariants.
        $variants = PhoneFormattingService::matchVariants($phone);

        $query = self::where('user_type_id', self::$USER_TYPE);
        if (empty($variants)) {
            $query->where('phone', $phone);
        } else {
            $query->whereIn('phone', $variants);
        }

        if ($patientId) {
            $query->where('id', $patientId);
        }

        return $query->first();
    }

    /*
    |--------------------------------------------------------------------------
    | CRUD Methods
    |--------------------------------------------------------------------------
    */

    public static function createRecord(array $data, int $flag = 0): self|string
    {
        if ($flag === 1) {
            $existing = self::where('phone', $data['phone'])->first();
            if ($existing) {
                return 'Patient is already exist';
            }
        }

        $record = self::create($data);
        AuditTrails::addEventLogger(self::$_table, 'create', $data, self::$_fillable, $record);

        return $record;
    }

    public static function updateRecord(int $id, array|false $data, array|false $appointmentData = false, array|false $patientData = false): ?self
    {
        if ($appointmentData) {
            if ($appointmentData['patient_id'] != 0) {
                Patients::find($appointmentData['patient_id'])?->toArray();
            }
            if (isset($appointmentData['patient_id_1']) && $appointmentData['patient_id'] == 0) {
                $appointmentData['patient_id'] = $appointmentData['patient_id_1'];
                $patientData['patient_id'] = $patientData['patient_id_1'];
            }

            $record = self::find($appointmentData['patient_id']);
            $existing = self::find($appointmentData['patient_id']);

            if ($existing) {
                AuditTrails::EditEventLogger(self::$_table, 'edit', $record, self::$_fillable, $existing, $appointmentData['patient_id']);
            } else {
                AuditTrails::addEventLogger(self::$_table, 'create', $record, self::$_fillable, $record);
            }

            return $record;
        }

        $record = self::find($id);
        if (! $record) {
            return null;
        }

        $oldData = $record->toArray();
        $record->update($data);
        AuditTrails::EditEventLogger(self::$_table, 'edit', $record, self::$_fillable, $oldData, $id);

        return $record;
    }

    public static function DeleteRecord(int $id): array
    {
        $patient = self::getData($id);

        if (! $patient) {
            return ['status' => false, 'message' => 'Resource not found.'];
        }

        if (self::isChildExists($id, auth()->user()->account_id)) {
            return ['status' => false, 'message' => 'Lead or Appointment exists, unable to delete resource'];
        }

        $patient->delete();
        AuditTrails::deleteEventLogger(self::$_table, 'delete', self::$_fillable, $id);

        return ['status' => true, 'message' => 'Record has been deleted successfully.'];
    }

    /*
    |--------------------------------------------------------------------------
    | Child Record Checks
    |--------------------------------------------------------------------------
    */

    public static function isChildExists(int $id, int $accountId): bool
    {
        return Leads::where(['patient_id' => $id, 'account_id' => $accountId])->exists()
            || Appointments::where(['patient_id' => $id, 'account_id' => $accountId])->exists()
            || CustomFormFeedbacks::where(['reference_id' => $id, 'account_id' => $accountId])->exists()
            || Documents::where('user_id', $id)->exists()
            || Packages::where(['patient_id' => $id, 'account_id' => $accountId])->exists()
            || Measurement::where('patient_id', $id)->exists()
            || Medical::where('patient_id', $id)->exists()
            || Invoices::where(['patient_id' => $id, 'account_id' => $accountId])->exists();
    }

    public static function getChildRecordsDetails(int $id, int $accountId): array
    {
        $checks = [
            'Leads' => Leads::where(['patient_id' => $id, 'account_id' => $accountId])->count(),
            'Appointments' => Appointments::where(['patient_id' => $id, 'account_id' => $accountId])->count(),
            'Custom Forms' => CustomFormFeedbacks::where(['reference_id' => $id, 'account_id' => $accountId])->count(),
            'Documents' => Documents::where('user_id', $id)->count(),
            'Packages' => Packages::where(['patient_id' => $id, 'account_id' => $accountId])->count(),
            'Measurements' => Measurement::where('patient_id', $id)->count(),
            'Medical Records' => Medical::where('patient_id', $id)->count(),
            'Invoices' => Invoices::where(['patient_id' => $id, 'account_id' => $accountId])->count(),
        ];

        return array_filter(
            array_map(fn (string $label, int $count): ?string => $count > 0 ? "{$label} ({$count})" : null, array_keys($checks), $checks)
        );
    }
}
