<?php

namespace App\Models;

use App\Helpers\GeneralFunctions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class Patients extends BaseModal
{
    use SoftDeletes;

    protected $table = 'users';

    protected static string $_table = 'users';

    protected static int $USER_TYPE = 3;

    protected static array $_fillable = [
        'name', 'email', 'phone', 'main_account', 'gender',
        'cnic', 'dob', 'address', 'referred_by', 'user_type_id',
    ];

    protected $fillable = [
        'name', 'email', 'password', 'remember_token', 'phone',
        'main_account', 'gender', 'cnic', 'dob', 'address',
        'referred_by', 'active', 'user_type_id', 'resource_type_id',
        'account_id', 'created_by', 'updated_by', 'image_src',
    ];

    protected $casts = [
        'active' => 'boolean',
        'dob' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Accessors & Mutators
    |--------------------------------------------------------------------------
    */

    /**
     * Patient code in C-{id} format (e.g. C-1234).
     */
    protected function patientCode(): Attribute
    {
        return Attribute::make(
            get: fn() => "C-{$this->id}",
        );
    }

    /**
     * Profile image URL with fallback to default avatar.
     */
    protected function profileImageUrl(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->image_src
                ? asset('storage/patient_image/' . $this->image_src)
                : asset('images/default-avatar.png'),
        );
    }

    /**
     * Formatted phone number for display.
     */
    protected function formattedPhone(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->phone ? GeneralFunctions::prepareNumber4Call($this->phone) : null,
        );
    }

    /**
     * Whether this patient is currently active.
     */
    protected function isActive(): Attribute
    {
        return Attribute::make(
            get: fn() => (bool) $this->active,
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
        if (!$phone) {
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

    public static function getAll(int $account_id)
    {
        return self::patientsOnly()->active()->forAccount($account_id)->get();
    }

    public static function getActiveOnly(int|array|false $patientId = false)
    {
        $query = self::patientsOnly()->active();

        if ($patientId) {
            $query->whereIn('id', (array) $patientId);
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Optimized patient search with caching (50-100X faster than legacy methods).
     * Use for all patient searches: referred_by, treatments, plans, etc.
     */
    public static function getPatientSearchOptimized(string $name, int $account_id)
    {
        $cacheKey = "patient_search_{$account_id}_" . md5($name);

        return Cache::remember($cacheKey, 300, function () use ($name, $account_id) {
            $cleaned = strtr($name, [' ' => '', '-' => '', '+' => '', 'C-' => '', 'c-' => '']);

            if (ctype_digit($cleaned)) {
                $phone = $cleaned[0] === '0' ? substr($cleaned, 1) : $cleaned;
                if (isset($phone[1]) && $phone[0] === '9' && $phone[1] === '2') {
                    $phone = substr($phone, 2);
                }

                return DB::select(
                    "SELECT DISTINCT name, id, phone, gender, cnic, email, dob, address
                     FROM users
                     WHERE user_type_id = 3 AND active = 1 AND account_id = ?
                       AND (phone = ? OR phone LIKE ? OR phone = ? OR phone LIKE ? OR id = ?)
                     ORDER BY CASE
                         WHEN phone = ? THEN 1 WHEN phone = ? THEN 2 WHEN id = ? THEN 3 ELSE 4
                     END, id DESC
                     LIMIT 10",
                    [$account_id, $phone, $phone.'%', $cleaned, $cleaned.'%', $cleaned, $phone, $cleaned, $cleaned]
                );
            }

            return DB::select(
                "SELECT DISTINCT name, id, phone, gender, cnic, email, dob, address
                 FROM users
                 WHERE user_type_id = 3 AND active = 1 AND account_id = ? AND name LIKE ?
                 ORDER BY id DESC LIMIT 10",
                [$account_id, $name.'%']
            );
        });
    }

    /**
     * Ajax patient search by ID or name (with membership data for orders).
     */
    public static function getPatientidAjaxOrder(string $name, int $account_id)
    {
        $users = collect();

        // Search by C-ID format
        if (stripos($name, 'C-') !== false) {
            $cleanId = str_replace(['C-', 'c-'], '', $name);
            $users = self::patientsOnly()->active()->forAccount($account_id)
                ->where('id', $cleanId)
                ->select('name', 'id', 'phone')
                ->get();
        } elseif (is_numeric($name)) {
            $users = self::patientsOnly()->active()->forAccount($account_id)
                ->where('id', $name)
                ->select('name', 'id', 'phone')
                ->get();
        }

        // Fallback to name/phone search
        if ($users->isEmpty()) {
            $search = GeneralFunctions::patientSearch($name);
            $phoneNumeric = GeneralFunctions::clearnString($search);

            $query = self::patientsOnly()->active()->forAccount($account_id);

            $users = is_numeric($phoneNumeric)
                ? $query->where('phone', 'LIKE', '%' . GeneralFunctions::cleanNumber($search) . '%')
                    ->select('name', 'id', 'phone')->get()
                : $query->where('name', 'LIKE', "%{$search}%")
                    ->select('name', 'id', 'phone')->get();
        }

        // Eager-load membership data to avoid N+1
        $patientIds = $users->pluck('id');
        $memberships = Membership::whereIn('patient_id', $patientIds)
            ->where('end_date', '>=', now())
            ->orderByDesc('end_date')
            ->get()
            ->keyBy('patient_id');

        foreach ($users as $user) {
            $membership = $memberships->get($user->id);
            $user->membership_code = $membership?->code ?? 'N/A';
            $user->membership_status = $membership ? 'Active' : 'Inactive';
            $user->membership_start_date = $membership?->start_date;
            $user->membership_end_date = $membership?->end_date;
            $user->membership_type_id = $membership?->membership_type_id;
        }

        return $users;
    }

    /**
     * @deprecated Use getPatientSearchOptimized() instead
     */
    public static function getPatientidAjax(string $name, int $account_id)
    {
        if (stripos($name, 'C-') !== false) {
            $cleanId = str_replace(['C-', 'c-'], '', $name);
            return self::patientsOnly()->active()->forAccount($account_id)
                ->where('id', $cleanId)
                ->select('name', 'id', 'phone')
                ->get();
        }

        if (is_numeric($name)) {
            $users = self::patientsOnly()->active()->forAccount($account_id)
                ->where('id', $name)
                ->select('name', 'id', 'phone')
                ->get();
            if ($users->isNotEmpty()) {
                return $users;
            }
        }

        $search = GeneralFunctions::patientSearch($name);
        $phoneNumeric = GeneralFunctions::clearnString($search);

        $query = self::patientsOnly()->active()->forAccount($account_id);

        if (is_numeric($phoneNumeric)) {
            $phone = GeneralFunctions::cleanNumber($search);
            return $query->where('phone', 'LIKE', "%{$phone}%")
                ->select('name', 'id', 'phone')->get();
        }

        return $query->where('name', 'LIKE', "%{$search}%")
            ->select('name', 'id', 'phone')->get();
    }

    public static function getPatientPhoneAjax(string $phone, int $account_id)
    {
        return self::patientsOnly()->active()->forAccount($account_id)
            ->where('phone', 'LIKE', "%{$phone}%")
            ->select('name', 'id', 'phone')
            ->get();
    }

    public static function getByPhone(string $phone, int|false $account_id = false, int|false $patient_id = false): ?self
    {
        $query = self::where('phone', $phone)
            ->where('user_type_id', self::$USER_TYPE);

        if ($patient_id) {
            $query->where('id', $patient_id);
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

    public static function updateRecord(int $id, array $data, array|false $appointmentData = false, array|false $patientData = false): ?self
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
        if (!$record) {
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

        if (!$patient) {
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

    public static function isChildExists(int $id, int $account_id): bool
    {
        return Leads::where(['patient_id' => $id, 'account_id' => $account_id])->exists()
            || Appointments::where(['patient_id' => $id, 'account_id' => $account_id])->exists()
            || CustomFormFeedbacks::where(['reference_id' => $id, 'account_id' => $account_id])->exists()
            || Documents::where('user_id', $id)->exists()
            || Packages::where(['patient_id' => $id, 'account_id' => $account_id])->exists()
            || Measurement::where('patient_id', $id)->exists()
            || Medical::where('patient_id', $id)->exists()
            || Invoices::where(['patient_id' => $id, 'account_id' => $account_id])->exists();
    }

    public static function getChildRecordsDetails(int $id, int $account_id): array
    {
        $checks = [
            'Leads' => Leads::where(['patient_id' => $id, 'account_id' => $account_id])->count(),
            'Appointments' => Appointments::where(['patient_id' => $id, 'account_id' => $account_id])->count(),
            'Custom Forms' => CustomFormFeedbacks::where(['reference_id' => $id, 'account_id' => $account_id])->count(),
            'Documents' => Documents::where('user_id', $id)->count(),
            'Packages' => Packages::where(['patient_id' => $id, 'account_id' => $account_id])->count(),
            'Measurements' => Measurement::where('patient_id', $id)->count(),
            'Medical Records' => Medical::where('patient_id', $id)->count(),
            'Invoices' => Invoices::where(['patient_id' => $id, 'account_id' => $account_id])->count(),
        ];

        return array_filter(
            array_map(fn($label, $count) => $count > 0 ? "{$label} ({$count})" : null, array_keys($checks), $checks)
        );
    }

}
