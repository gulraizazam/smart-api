<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\EncryptedLegacy;
use App\Models\Concerns\GuardsTenantBoundary;
use App\Services\Dashboard\Support\ResourceScopeResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    // Sanctum's HasApiTokens stays for the legacy /api/* endpoints and
    // the live SPA. Passport's password grant runs through /oauth/token
    // without touching this model — no Passport trait required here.
    use GuardsTenantBoundary, HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    // Audit-log policy for this model lives in config/activity_log.php
    // (tiers → User::class = 'security', whitelists → User::class = [...]).
    // No model-level trait is required for auto-observed models — the
    // observer is attached centrally by AppServiceProvider.

    /**
     * H2 — failed-login lockout policy. Five wrong attempts → 15 minute
     * account-level lock (independent of the existing throttle:5,1 IP
     * rate limiter, which is cache-keyed and per-IP). The DB-backed
     * lockout survives cache flushes and follows the account, not the
     * source address.
     */
    public const MAX_FAILED_LOGIN_ATTEMPTS = 5;

    public const LOGIN_LOCKOUT_MINUTES = 15;

    /**
     * Tenant-boundary guard column list. Includes the standard `account_id`
     * (multi-tenant escape) plus User-specific privilege-bearing columns.
     * `user_type_id` controls role group; `is_admin` (if present) flips
     * super-admin. Both must be set explicitly by service code, never via
     * mass-assignment.
     *
     * Implemented as a method override (not a property) because PHP 8.3+
     * forbids redeclaring a trait property with a different default value.
     *
     * @return array<int, string>
     */
    protected static function tenantGuardedColumns(): array
    {
        return [
            'account_id',
            'user_type_id',
            'is_admin',
        ];
    }

    protected static int $PATIENT_GROUP = 3;

    protected static int $DOCTOR_GROUP = 5;

    protected $fillable = [
        'name', 'email', 'password', 'phone', 'cnic', 'main_account', 'gender',
        'dob', 'commission', 'can_perform_consultation',
        'user_type_id', 'resource_type_id', 'referred_by', 'account_id',
        'active', 'select_all', 'is_advance_eligible', 'hr_managed',
    ];

    /**
     * Audit-trail fillable list. NEVER include 'password' or other secrets here —
     * AuditTrails::addEventLogger captures values verbatim from the pre-mutation
     * request array. The AuditTrails denylist mask (see AuditTrails.php) is the
     * primary defense; this list is the belt-and-suspenders second line.
     */
    protected static array $_fillable = [
        'name', 'email', 'phone', 'main_account', 'gender',
        'dob', 'commission', 'can_perform_consultation',
        'user_type_id', 'resource_type_id', 'referred_by', 'active', 'select_all',
    ];

    protected static string $_table = 'users';

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'dob' => 'date',
            'active' => 'integer',
            'commission' => 'decimal:2',
            'can_perform_consultation' => 'boolean',
            // CNIC is encrypted at rest. Equality search on this column is
            // currently used in LeadService::applyReportFilters and will
            // return false negatives once any matching row has been
            // re-saved through the cast. Add a blind-index column
            // (cnic_hash) when exact-match search is needed.
            'cnic' => EncryptedLegacy::class,
            // H2 — lockout
            'failed_login_attempts' => 'integer',
            'locked_until' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Keep `phone_normalized` in lockstep with `phone` so the indexed
        // search path (WHERE phone_normalized LIKE '302%') never diverges
        // from the canonical column. Strips every non-digit. Cheap regex
        // — runs only on save, only when phone changed.
        static::saving(function (User $user): void {
            if ($user->isDirty('phone')) {
                $raw = (string) ($user->phone ?? '');
                $digits = preg_replace('/\D+/', '', $raw) ?? '';
                $user->phone_normalized = $digits === '' ? null : $digits;
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | H2 — Lockout helpers
    |--------------------------------------------------------------------------
    */

    /**
     * True if the account is currently locked out from logging in.
     */
    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    /**
     * Increment the failed-login counter and lock the account if the
     * threshold is reached. Idempotent: safe to call from any failed
     * branch (bad password, inactive, etc.).
     */
    public function recordFailedLogin(): void
    {
        // Use raw query update to avoid mass-assignment guards firing on
        // these counter columns and to skip the model's `password` mutator.
        $next = (int) $this->failed_login_attempts + 1;
        $attrs = ['failed_login_attempts' => $next];

        if ($next >= self::MAX_FAILED_LOGIN_ATTEMPTS) {
            $attrs['locked_until'] = now()->addMinutes(self::LOGIN_LOCKOUT_MINUTES);
            $attrs['failed_login_attempts'] = 0;
        }

        static::withoutEvents(fn () => static::whereKey($this->getKey())->update($attrs));
        $this->forceFill($attrs);
    }

    /**
     * Reset the failed-login counter and clear any active lock. Called
     * after a verified successful login.
     */
    public function recordSuccessfulLogin(): void
    {
        if ($this->failed_login_attempts === 0 && $this->locked_until === null) {
            return;
        }
        static::withoutEvents(fn () => static::whereKey($this->getKey())->update([
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ]));
        $this->forceFill(['failed_login_attempts' => 0, 'locked_until' => null]);
    }

    protected function fullName(): Attribute
    {
        return Attribute::get(
            fn (): string => ucfirst($this->name).' - '.strtolower($this->email)
        );
    }

    /**
     * Build the auth-payload shape the SPA expects: raw user attributes
     * plus the effective permission gate names (direct + role-inherited)
     * and the primary role name.
     *
     * The SPA's `usePermissions().has()` falls back to "allow all" when
     * the `permissions` field is missing (a backward-compat affordance
     * for legacy bearer-token clients), so any endpoint that returns a
     * user without permissions silently leaks gated menu entries —
     * e.g. Scheduling Shifts / Business Closures showed up for FDM
     * after login because `/api/login` returned the bare `$user` model.
     *
     * Use this from every endpoint that hands a user back to the SPA:
     *   - POST /api/login (sanctum)
     *   - POST /api/v2/auth/login (passport)
     *   - GET  /api/user (boot refresh)
     *
     * @return array<string, mixed>
     */
    public function toAuthPayload(): array
    {
        $payload = $this->toArray();

        // Preserve `api_token` if the caller set it on the model
        // (sanctum login does this so the SPA can store the PAT).
        if (isset($this->api_token)) {
            $payload['api_token'] = $this->api_token;
        }

        $payload['role']        = $this->roles->pluck('name')->first();
        $payload['permissions'] = $this->getAllPermissions()
            ->pluck('name')
            ->values()
            ->all();

        // Authoritative ACL-scoped centre assignment so the SPA can
        // auto-select + lock centre filters when the user is bound to a
        // single branch. Mirrors ResourceScopeResolver semantics:
        //   null  → company-wide (all centres)
        //   [id]  → exactly one centre (SPA locks every centre selector to it)
        //   [...] → a regional subset
        $payload['assigned_centre_ids'] = app(ResourceScopeResolver::class)
            ->allowedBranchIds($this);

        return $payload;
    }

    public function membership(): HasOne
    {
        return $this->hasOne(Membership::class, 'patient_id')->orderByDesc('id');
    }

    public function employeeDetail(): HasOne
    {
        return $this->hasOne(EmployeeDetail::class);
    }

    public function employeeDocuments(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    public function scopeIsActive(Builder $query, int $status = 1): Builder
    {
        return $query->where('active', $status);
    }

    public function refund(): HasMany
    {
        return $this->hasMany(Refunds::class, 'patient_id');
    }

    public function invoice(): HasMany
    {
        return $this->hasMany(Invoices::class, 'patient_id');
    }

    public function package(): HasMany
    {
        return $this->hasMany(Packages::class, 'patient_id');
    }

    protected function password(): Attribute
    {
        return Attribute::set(
            fn (?string $value): ?string => $value
                ? (app('hash')->needsRehash($value) ? Hash::make($value) : $value)
                : null
        );
    }

    public function role(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user');
    }

    public function user_roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_has_users');
    }

    public function getRoles(): string
    {
        return $this->user_roles()->pluck('name')->implode(',');
    }

    /**
     * Build the serialized payload returned by /api/login and /api/user.
     * The SPA's `usePermissions().has(gate)` reads `permissions` to decide
     * which menu items / pages to show; omitting it falls back to allow-all
     * (legacy compat) and silently exposes every gated surface.
     */
    public function toAuthPayload(): array
    {
        $payload = $this->toArray();
        $payload['role'] = $this->getRoleNames()->first();

        // Expand the permission set through PermissionAliasMap so SPA
        // `usePermissions().has(gate)` works against either the legacy
        // snake_case or the new dotted slug — the role editor only saves
        // the dotted side, but most SPA call-sites still reference the
        // legacy names (see plans.tsx, etc.). Same Gate::before bridge runs
        // server-side; this mirrors that behaviour for the client.
        $names = $this->getAllPermissions()->pluck('name')->all();
        $expanded = [];
        foreach ($names as $name) {
            $expanded[$name] = true;
            $aliased = \App\Support\PermissionAliasMap::aliasFor($name);
            if ($aliased !== null) {
                $expanded[$aliased] = true;
            }
        }
        $payload['permissions'] = array_values(array_keys($expanded));

        return $payload;
    }

    public function doctorhaslocation(): HasMany
    {
        return $this->hasMany(DoctorHasLocations::class, 'user_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Leads::class, 'created_by');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointments::class, 'created_by');
    }

    public function appointmentsPatient(): HasMany
    {
        return $this->hasMany(Appointments::class, 'patient_id');
    }

    public function appointmentsDoc(): HasMany
    {
        return $this->hasMany(Appointments::class, 'doctor_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Accounts::class);
    }

    public function audit(): HasMany
    {
        return $this->hasMany(AuditTrails::class, 'user_id');
    }

    public function patients(): HasMany
    {
        return $this->hasMany(Patients::class, 'created_by');
    }

    public function referredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by')->withTrashed();
    }

    public function user_has_locations(): HasMany
    {
        return $this->hasMany(UserHasLocations::class, 'user_id')->withoutGlobalScope(SoftDeletingScope::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Locations::class, 'location_id')->withTrashed();
    }

    public function user_has_warehouse(): HasMany
    {
        return $this->hasMany(UserHasWarehouse::class, 'user_id')->withoutGlobalScope(SoftDeletingScope::class);
    }

    public function role_has_users(): HasMany
    {
        return $this->hasMany(RoleHasUsers::class, 'user_id')->withoutGlobalScope(SoftDeletingScope::class);
    }

    public function doctor_has_services(): HasMany
    {
        return $this->hasMany(DoctorHasServices::class, 'user_id')->withoutGlobalScope(SoftDeletingScope::class);
    }

    public function packagesadvances(): HasMany
    {
        return $this->hasMany(PackageAdvances::class, 'patient_id');
    }

    public function measurement(): HasMany
    {
        return $this->hasMany(Measurement::class, 'user_id');
    }

    public function audit_field_before(): HasMany
    {
        return $this->hasMany(AuditTrailChanges::class, 'field_before');
    }

    public function audit_field_after(): HasMany
    {
        return $this->hasMany(AuditTrailChanges::class, 'field_after');
    }

    public static function getData(int $id): ?static
    {
        return static::where([
            ['id', '=', $id],
            ['account_id', '=', Auth::user()->account_id],
        ])->first();
    }

    public static function getUsers()
    {
        return static::where('account_id', '=', Auth::user()->account_id)
            ->whereNull('resource_type_id')
            ->pluck('name', 'id');
    }

    public static function isExists(int $id, ?int $accountId = null): bool
    {
        return DoctorHasLocations::where('is_allocated', 1)->where('user_id', $id)->exists()
            || Appointments::where('doctor_id', $id)->exists();
    }

    /**
     * @deprecated Move to PatientService when optimizing Patients module
     */
    public static function getPatients()
    {
        return static::where([
            ['user_type_id', '=', config('constants.patient_id')],
            ['account_id', '=', Auth::user()->account_id],
        ])->get();
    }

    /**
     * @deprecated Move to PatientService when optimizing Patients module
     */
    public static function finduser(int $id): ?static
    {
        return static::where([
            ['account_id', '=', Auth::user()->account_id],
            ['id', '=', $id],
        ])->first();
    }

    /**
     * @deprecated Move to appropriate service when optimizing related module
     */
    public static function getAllRecords(int $accountId)
    {
        return static::where('account_id', $accountId)
            ->whereNotIn('user_type_id', [static::$PATIENT_GROUP])
            ->get();
    }

    /**
     * @deprecated Move to PatientService when optimizing Patients module
     */
    public static function getAllPatientRecords(int $accountId, array $ids = [])
    {
        $query = static::where('account_id', $accountId)
            ->whereIn('user_type_id', [static::$PATIENT_GROUP]);

        if (! empty($ids)) {
            $query->whereIn('id', $ids);
        }

        return $query->get();
    }

    /**
     * @deprecated Move to appropriate service when optimizing related module
     */
    public static function getAllActiveRecords(int $accountId, array|int|false $locationId = false)
    {
        $locationIds = match (true) {
            $locationId === false => null,
            is_array($locationId) => $locationId,
            default => [$locationId],
        };

        if ($locationIds) {
            return static::join('user_has_locations', 'users.id', '=', 'user_has_locations.user_id')
                ->where('users.user_type_id', '!=', static::$PATIENT_GROUP)
                ->where('users.active', 1)
                ->where('users.account_id', $accountId)
                ->whereIn('user_has_locations.location_id', $locationIds)
                ->get();
        }

        return static::where(['active' => 1, 'account_id' => $accountId])
            ->whereNotIn('user_type_id', [static::$PATIENT_GROUP])
            ->get();
    }

    /**
     * @deprecated Move to appropriate service when optimizing related module
     */
    public static function getAllActiveEmployeeRecords(int $accountId, array|int|false $locationId = false)
    {
        $locationIds = match (true) {
            $locationId === false => null,
            is_array($locationId) => $locationId,
            default => [$locationId],
        };

        if ($locationIds) {
            return static::join('user_has_locations', 'users.id', '=', 'user_has_locations.user_id')
                ->where('users.user_type_id', '!=', static::$PATIENT_GROUP)
                ->where('users.active', 1)
                ->where('users.account_id', $accountId)
                ->whereIn('user_has_locations.location_id', $locationIds)
                ->select('users.id', 'users.name')
                ->get();
        }

        return static::where(['active' => 1, 'account_id' => $accountId])
            ->whereNotIn('user_type_id', [static::$PATIENT_GROUP])
            ->get();
    }

    /**
     * @deprecated Move to DoctorService when optimizing Doctors module
     */
    public static function getAllActivePractionersRecords(int $accountId, array|int|false $locationId = false)
    {
        $locationIds = match (true) {
            $locationId === false => null,
            is_array($locationId) => $locationId,
            default => [$locationId],
        };

        if ($locationIds) {
            return static::join('doctor_has_locations', 'users.id', '=', 'doctor_has_locations.user_id')
                ->where('users.user_type_id', '!=', static::$PATIENT_GROUP)
                ->where('users.active', 1)
                ->where('users.account_id', $accountId)
                ->whereIn('doctor_has_locations.location_id', $locationIds)
                ->select('users.id', 'users.name')
                ->get();
        }

        return static::where(['active' => 1, 'account_id' => $accountId])
            ->whereNotIn('user_type_id', [static::$PATIENT_GROUP])
            ->get();
    }

    /**
     * @deprecated Move to appropriate service when optimizing related module
     */
    public static function getAllSystemUsersActiveRecords(int $accountId)
    {
        return static::where(['active' => 1, 'account_id' => $accountId])
            ->whereNotIn('user_type_id', [static::$PATIENT_GROUP, static::$DOCTOR_GROUP])
            ->get();
    }

    /**
     * @deprecated Move to appropriate service when optimizing related module
     */
    public static function getActiveOnly(
        array|int|false $locationId = false,
        int|false $accountId = false,
        array|int|false $userId = false,
        bool $pluckColumns = true,
    ) {
        $locationIds = match (true) {
            $locationId === false => null,
            is_array($locationId) => $locationId,
            default => [$locationId],
        };

        $userIds = match (true) {
            $userId === false => null,
            is_array($userId) => $userId,
            default => [$userId],
        };

        $query = $locationIds
            ? static::buildLocationBasedQuery($locationIds, $accountId)
            : static::buildDirectQuery($accountId);

        if ($userIds) {
            $query->whereIn('users.id', $userIds);
        }

        $results = $query->get();

        if ($pluckColumns) {
            $pluckKey = $locationIds ? 'user_id' : 'id';

            return $results->pluck('name', $pluckKey);
        }

        return $results;
    }

    private static function buildLocationBasedQuery(array $locationIds, int|false $accountId): Builder
    {
        $query = static::join('user_has_locations', function ($join) use ($accountId) {
            $join->on('users.id', '=', 'user_has_locations.user_id')
                ->where('users.user_type_id', '=', config('constants.application_user_id'))
                ->where('users.active', '=', 1);

            if ($accountId) {
                $join->where('users.account_id', '=', $accountId);
            }
        })->whereIn('user_has_locations.location_id', $locationIds);

        return $query;
    }

    private static function buildDirectQuery(int|false $accountId): Builder
    {
        $query = static::where('users.user_type_id', '=', config('constants.application_user_id'))
            ->where('users.active', '=', 1);

        if ($accountId) {
            $query->where('users.account_id', '=', $accountId);
        }

        return $query;
    }
}
