<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\EncryptedLegacy;
use App\Helpers\GeneralFunctions;
use App\Helpers\PatientAccessScope;
use App\Services\PatientManagement\PatientSearchService;
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

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'dob' => 'date',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            // Encrypted at rest. See User::casts() for the equality-search caveat.
            'cnic' => EncryptedLegacy::class,
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

    protected function profileImageUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->image_src
                ? route('admin.files.patient_image', ['filename' => $this->image_src])
                : asset('images/default-avatar.png'),
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
        // Reject empty / single-character queries before they hit the LIKE
        // branch. Without this, `?search=` produces `name LIKE '%'` and
        // `?search=%` / `?search=_` collapse to wildcard scans, both of
        // which dump the 10 most-recent patients with PII (phone, cnic,
        // email, dob). Cache is also bypassed for short input
        // so attackers cannot bloat it with random short strings.
        $name = trim($name);
        if (strlen($name) < 2) {
            return [];
        }

        $canViewContact = Gate::allows('contact');

        [$scopeSql, $scopeBindings] = PatientAccessScope::rawClause('users.id');
        $cacheKey = "patient_search_{$accountId}_".PatientAccessScope::cacheSuffix().'_'.md5($name);

        $rows = Cache::remember($cacheKey, 300, function () use ($name, $accountId, $scopeSql, $scopeBindings): array {
            $cleaned = strtr($name, [' ' => '', '-' => '', '+' => '', 'C-' => '', 'c-' => '']);

            if (ctype_digit($cleaned)) {
                $phone = $cleaned[0] === '0' ? substr($cleaned, 1) : $cleaned;
                if (isset($phone[1]) && $phone[0] === '9' && $phone[1] === '2') {
                    $phone = substr($phone, 2);
                }

                $rows = DB::select(
                    "SELECT DISTINCT name, id, phone, gender, cnic, email, dob
                     FROM users
                     WHERE user_type_id = 3 AND active = 1 AND account_id = ?
                       AND (phone = ? OR phone LIKE ? OR phone = ? OR phone LIKE ? OR id = ? OR id LIKE ?)
                       {$scopeSql}
                     ORDER BY CASE
                         WHEN phone = ? THEN 1 WHEN phone = ? THEN 2 WHEN id = ? THEN 3 ELSE 4
                     END, id DESC
                     LIMIT 10",
                    array_merge(
                        [$accountId, $phone, $phone.'%', $cleaned, $cleaned.'%', $cleaned, $cleaned.'%'],
                        $scopeBindings,
                        [$phone, $cleaned, $cleaned]
                    )
                );

                return self::decryptCnicColumn($rows);
            }

            // Escape SQL LIKE metacharacters in user-supplied input. PDO
            // parameter binding prevents SQL injection but does NOT escape
            // `%` / `_` inside the bound LIKE pattern, so an authenticated
            // user could pass `%_%_` to bypass the prefix-search intent.
            // Backslash is escaped first to avoid double-escaping the
            // escape sequences we add for `%` and `_`.
            $escaped = addcslashes($name, '\\%_');

            $rows = DB::select(
                "SELECT DISTINCT name, id, phone, gender, cnic, email, dob
                 FROM users
                 WHERE user_type_id = 3 AND active = 1 AND account_id = ? AND name LIKE ?
                   {$scopeSql}
                 ORDER BY id DESC LIMIT 10",
                array_merge([$accountId, $escaped.'%'], $scopeBindings)
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
     * Raw queries bypass the Eloquent EncryptedLegacy cast on this model, so
     * any path that selects `cnic` via DB::select must run results through
     * this helper to avoid leaking the base64 envelope to API consumers.
     * Mirrors the cast's tolerance for legacy plaintext rows: if Crypt
     * fails, the raw value is returned unchanged.
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

    public static function getPatientidAjaxOrder(string $name, int $accountId): Collection
    {
        $canViewContact = Gate::allows('contact');
        $users = collect();

        if (str_contains(strtolower($name), 'c-')) {
            $cleanId = str_replace(['C-', 'c-'], '', $name);
            $query = self::patientsOnly()->active()->forAccount($accountId)
                ->where('id', $cleanId);
            PatientAccessScope::applyTo($query);
            $users = $query->select('name', 'id', 'phone')->get();
        } elseif (is_numeric($name)) {
            $query = self::patientsOnly()->active()->forAccount($accountId)
                ->where('id', $name);
            PatientAccessScope::applyTo($query);
            $users = $query->select('name', 'id', 'phone')->get();
        }

        if ($users->isEmpty()) {
            $search = PatientSearchService::patientSearch($name);
            $phoneNumeric = GeneralFunctions::clearnString($search);

            $query = self::patientsOnly()->active()->forAccount($accountId);
            PatientAccessScope::applyTo($query);

            $users = is_numeric($phoneNumeric)
                ? $query->where('phone', 'LIKE', '%'.GeneralFunctions::cleanNumber($search).'%')
                    ->select('name', 'id', 'phone')->get()
                : $query->where('name', 'LIKE', "%{$search}%")
                    ->select('name', 'id', 'phone')->get();
        }

        $patientIds = $users->pluck('id');
        $memberships = Membership::whereIn('patient_id', $patientIds)
            ->where('end_date', '>=', now())
            ->orderByDesc('end_date')
            ->get()
            ->keyBy('patient_id');

        foreach ($users as $user) {
            if (! $canViewContact) {
                $user->makeHidden('phone');
            }
            $membership = $memberships->get($user->id);
            $user->membership_code = $membership?->code ?? 'N/A';
            $user->membership_status = $membership ? 'Active' : 'Inactive';
            $user->membership_start_date = $membership?->start_date;
            $user->membership_end_date = $membership?->end_date;
            $user->membership_type_id = $membership?->membership_type_id;
        }

        return $users;
    }

    public static function getPatientidAjax(string $name, int $accountId): Collection
    {
        $canViewContact = Gate::allows('contact');
        $hidePhone = static function (Collection $users) use ($canViewContact): Collection {
            if (! $canViewContact) {
                $users->each(fn ($user) => $user->makeHidden('phone'));
            }

            return $users;
        };

        if (str_contains(strtolower($name), 'c-')) {
            $cleanId = str_replace(['C-', 'c-'], '', $name);
            $query = self::patientsOnly()->active()->forAccount($accountId)
                ->where('id', $cleanId);
            PatientAccessScope::applyTo($query);

            return $hidePhone($query->select('name', 'id', 'phone')->get());
        }

        if (is_numeric($name)) {
            $query = self::patientsOnly()->active()->forAccount($accountId)
                ->where('id', $name);
            PatientAccessScope::applyTo($query);
            $users = $query->select('name', 'id', 'phone')->get();
            if ($users->isNotEmpty()) {
                return $hidePhone($users);
            }
        }

        $search = PatientSearchService::patientSearch($name);
        $phoneNumeric = GeneralFunctions::clearnString($search);

        $query = self::patientsOnly()->active()->forAccount($accountId);
        PatientAccessScope::applyTo($query);

        if (is_numeric($phoneNumeric)) {
            $phone = GeneralFunctions::cleanNumber($search);

            return $hidePhone($query->where('phone', 'LIKE', "%{$phone}%")
                ->select('name', 'id', 'phone')->get());
        }

        return $hidePhone($query->where('name', 'LIKE', "%{$search}%")
            ->select('name', 'id', 'phone')->get());
    }

    public static function getPatientPhoneAjax(string $phone, int $accountId): Collection
    {
        // Phone-prefix lookup is meaningless — and leaks enumeration signal —
        // when the caller cannot view contact numbers. Deny outright.
        if (! Gate::allows('contact')) {
            return new Collection;
        }

        $query = self::patientsOnly()->active()->forAccount($accountId)
            ->where('phone', 'LIKE', "%{$phone}%");
        PatientAccessScope::applyTo($query);

        return $query->select('name', 'id', 'phone')->get();
    }

    public static function getByPhone(string $phone, int|false $accountId = false, int|false $patientId = false): ?self
    {
        $query = self::where('phone', $phone)
            ->where('user_type_id', self::$USER_TYPE);

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
