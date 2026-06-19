<?php

declare(strict_types=1);

namespace App\Services\UserManagement;

use App\Helpers\Filters;
use App\Helpers\Widgets\LocationsWidget;
use App\Http\Resources\User\ApplicationUserResource;
use App\Models\AuditTrails;
use App\Models\Locations;
use App\Models\Patients;
use App\Models\RoleHasUsers;
use App\Models\User;
use App\Models\UserHasLocations;
use App\Models\UserHasWarehouse;
use App\Models\Warehouse;
use App\Services\Phone\PhoneFormattingService;
use App\Services\Reports\Concerns\ParsesDateRange;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class ApplicationUserService
{
    use ParsesDateRange;

    private const FILTER_KEY = 'users';

    public function getDatatableData(array $params): array
    {
        $user = Auth::user();
        $canViewInactive = Gate::allows('view_inactive_users');

        $where = $this->buildWhereConditions($params, $user->id, $user->account_id);

        $baseQuery = User::leftJoin('user_has_locations', 'users.id', '=', 'user_has_locations.user_id')
            ->leftJoin('role_has_users', 'users.id', '=', 'role_has_users.user_id')
            ->whereNotIn('users.user_type_id', [
                Config::get('constants.practitioner_id'),
                Config::get('constants.patient_id'),
            ])
            ->where('users.email', '!=', config('constants.super_admin_email', 'superadmin@redsignal.net'))
            ->where('users.account_id', $user->account_id)
            ->when(! $canViewInactive, fn ($q) => $q->where('users.active', 1));

        foreach ($where as $condition) {
            $baseQuery->where($condition[0], $condition[1], $condition[2]);
        }

        $total = (clone $baseQuery)->distinct()->count('users.id');

        $users = (clone $baseQuery)
            ->select('users.*')
            ->distinct()
            ->with(['user_has_locations', 'role_has_users'])
            ->orderBy($params['orderBy'] ?? 'users.name', $params['order'] ?? 'asc')
            ->offset($params['offset'] ?? 0)
            ->limit($params['limit'] ?? 30)
            ->get();

        return [
            'data' => $this->formatDatatableData($users),
            'total' => $total,
        ];
    }

    private function buildWhereConditions(array $params, int $userId, ?int $accountId): array
    {
        $where = [];
        $applyFilter = $params['apply_filter'] ?? false;

        $where = $this->addFilter($where, $params, 'name', 'users.name', 'like', $userId, $applyFilter);
        $where = $this->addFilter($where, $params, 'email', 'users.email', 'like', $userId, $applyFilter);

        if (! empty($params['phone'])) {
            $phone = PhoneFormattingService::cleanNumber($params['phone']);
            $where[] = ['users.phone', 'like', "%{$phone}%"];
            Filters::put($userId, self::FILTER_KEY, 'phone', $params['phone']);
        } elseif ($applyFilter) {
            Filters::forget($userId, self::FILTER_KEY, 'phone');
        } elseif ($storedPhone = Filters::get($userId, self::FILTER_KEY, 'phone')) {
            $where[] = ['users.phone', 'like', '%'.PhoneFormattingService::cleanNumber($storedPhone).'%'];
        }

        $where = $this->addFilter($where, $params, 'gender', 'users.gender', '=', $userId, $applyFilter);
        $where = $this->addFilter($where, $params, 'location_id', 'user_has_locations.location_id', '=', $userId, $applyFilter);
        $where = $this->addFilter($where, $params, 'role_id', 'role_has_users.role_id', '=', $userId, $applyFilter);

        if (isset($params['status']) && $params['status'] !== '' && $params['status'] !== null) {
            $where[] = ['users.active', '=', (int) $params['status']];
            Filters::put($userId, self::FILTER_KEY, 'status', (int) $params['status']);
        } elseif ($applyFilter) {
            Filters::forget($userId, self::FILTER_KEY, 'status');
        } else {
            $storedStatus = Filters::get($userId, self::FILTER_KEY, 'status');
            if ($storedStatus !== null && $storedStatus !== '' && in_array($storedStatus, [0, 1, '0', '1'], true)) {
                $where[] = ['users.active', '=', (int) $storedStatus];
            }
        }

        if (! empty($params['created_at'])) {
            [$fromDay, $toDay] = self::parseDateRangeAsCarbonDay($params['created_at']);
            $where[] = ['users.created_at', '>=', $fromDay];
            $where[] = ['users.created_at', '<=', $toDay];
            Filters::put($userId, self::FILTER_KEY, 'created_at', $params['created_at']);
        } elseif ($applyFilter) {
            Filters::forget($userId, self::FILTER_KEY, 'created_at');
        }

        return $where;
    }

    private function addFilter(array $where, array $params, string $key, string $column, string $operator, int $userId, bool $applyFilter): array
    {
        if (! empty($params[$key])) {
            $value = $operator === 'like' ? "%{$params[$key]}%" : $params[$key];
            $where[] = [$column, $operator, $value];
            Filters::put($userId, self::FILTER_KEY, $key, $params[$key]);
        } elseif ($applyFilter) {
            Filters::forget($userId, self::FILTER_KEY, $key);
        } elseif ($storedValue = Filters::get($userId, self::FILTER_KEY, $key)) {
            $value = $operator === 'like' ? "%{$storedValue}%" : $storedValue;
            $where[] = [$column, $operator, $value];
        }

        return $where;
    }

    private function formatDatatableData(Collection $users): array
    {
        $locations = Locations::all()->getDictionary();
        $accountId = Auth::user()->account_id;

        ApplicationUserResource::$locationLookup = $locations;

        $locationIdsByUser = [];
        foreach ($users as $user) {
            $userHasLocations = $user->user_has_locations?->pluck('location_id') ?? collect();
            $locationIdsByUser[$user->id] = LocationsWidget::generatelocationArrayEdit($userHasLocations, $accountId, $user) ?: [];
        }

        ApplicationUserResource::$locationIdsByUser = $locationIdsByUser;

        return ApplicationUserResource::collection($users)->resolve();
    }

    public function getUserPermissions(): array
    {
        return [
            'edit' => Gate::allows('users_edit'),
            'change_password' => Gate::allows('users_change_password'),
            'active' => Gate::allows('users_active'),
            'inactive' => Gate::allows('users_inactive'),
            'delete' => Gate::allows('users_destroy'),
            'contact' => Gate::allows('contact'),
        ];
    }

    public function getFilterValues(): array
    {
        $accountId = Auth::user()->account_id;

        return [
            'roles' => Role::pluck('name', 'id'),
            'locations' => Locations::with('city')
                ->where('active', 1)
                ->where('account_id', $accountId)
                ->get()
                ->pluck('full_address', 'id'),
            'status' => config('constants.status'),
        ];
    }

    public function getActiveFilters(): array
    {
        return Filters::all(Auth::user()->id, self::FILTER_KEY);
    }

    public function getCreateData(): array
    {
        $accountId = Auth::user()->account_id;

        return [
            'roles' => Role::where('name', '!=', 'Super-Admin')->get(),
            'locations' => LocationsWidget::generateDropDownArray($accountId),
            'warehouse' => Warehouse::where('active', 1)->get(),
            'user' => (object) ['gender' => null, 'phone' => null],
        ];
    }

    public function create(array $data): User
    {
        $accountId = Auth::user()->account_id;

        $userData = [
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'phone' => PhoneFormattingService::cleanNumber($data['phone'] ?? ''),
            'gender' => $data['gender'] ?? null,
            'hr_managed' => (int) ($data['hr_managed'] ?? 1),
            'account_id' => $accountId,
            'main_account' => '0',
            'user_type_id' => Config::get('constants.application_user_id'),
        ];

        $user = User::create($userData);
        AuditTrails::addEventLogger('users', 'create', $userData, User::$_fillable ?? [], $user);

        $roles = $this->assignableRoleIds($data['roles'] ?? []);
        if (! empty($roles)) {
            $user->assignRole(Role::whereIn('id', $roles)->get());
            $this->syncRoleHasUsers($user, $roles);
        }

        if (! empty($data['centers'])) {
            $this->syncUserLocations($user, $data['centers']);
        }

        if (! empty($data['warehouse'])) {
            $this->syncUserWarehouses($user, $data['warehouse']);
        }

        return $user;
    }

    public function getEditData(int $id): ?array
    {
        $accountId = Auth::user()->account_id;
        $user = $this->findByAccountId($id, $accountId);

        if (! $user) {
            return null;
        }

        $userHasLocations = $user->user_has_locations->pluck('location_id');
        $userHasLocations = LocationsWidget::generatelocationArrayEdit($userHasLocations, $accountId, $user) ?: [];

        return [
            'roles' => Role::where('name', '!=', 'Super-Admin')->pluck('name', 'id'),
            'user' => $user,
            'locations' => LocationsWidget::generateDropDownArray($accountId),
            'warehouse' => Warehouse::where('active', 1)->get(),
            'user_has_locations' => $userHasLocations,
            'user_has_warehouse' => $user->user_has_warehouse->pluck('warehouse_id')->all(),
            'user_roles' => $user->user_roles()->pluck('id')->all(),
        ];
    }

    public function update(int $id, array $data): User
    {
        $accountId = Auth::user()->account_id;
        $user = $this->findByAccountId($id, $accountId);

        $oldData = $user->makeVisible(['password'])->toArray();

        if (($data['phone'] ?? null) === '***********' && isset($data['old_phone'])) {
            $data['phone'] = $data['old_phone'];
        }
        unset($data['old_phone']);

        $userData = [
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => PhoneFormattingService::cleanNumber($data['phone'] ?? ''),
            'gender' => $data['gender'] ?? null,
            'hr_managed' => (int) ($data['hr_managed'] ?? 1),
        ];

        $user->update($userData);
        AuditTrails::editEventLogger('users', 'Edit', $userData, User::$_fillable ?? [], $oldData, $id);

        $existingRoleIds = $user->roles->pluck('id')->all();
        $roles = $this->assignableRoleIds($data['roles'] ?? [], $existingRoleIds);
        if (! empty($roles)) {
            $user->syncRoles(Role::whereIn('id', $roles)->get());
            $user->role_has_users()->forceDelete();
            $this->syncRoleHasUsers($user, $roles);
        }

        if (! empty($data['centers'])) {
            $user->user_has_locations()->forceDelete();
            $this->syncUserLocations($user, $data['centers']);
        }

        $user->user_has_warehouse()->delete();
        if (! empty($data['warehouse'])) {
            $this->syncUserWarehouses($user, $data['warehouse']);
        }

        return $user;
    }

    public function delete(int $id): bool
    {
        $user = $this->findByAccountId($id);

        if (! $user) {
            return false;
        }

        $deleted = $user->delete();
        AuditTrails::deleteEventLogger('users', 'delete', User::$_fillable ?? [], $id);

        return $deleted;
    }

    public function changeStatus(int $id, int $status): bool
    {
        $user = $this->findByAccountId($id);

        if (! $user) {
            return false;
        }

        $user->update(['active' => $status]);

        match ($status) {
            1 => AuditTrails::activeEventLogger('users', 'active', User::$_fillable ?? [], $id),
            default => AuditTrails::InactiveEventLogger('users', 'inactive', User::$_fillable ?? [], $id),
        };

        return true;
    }

    public function changePassword(int $id, string $password): bool
    {
        $user = $this->findByAccountId($id);

        if (! $user) {
            return false;
        }

        $oldData = $user->makeVisible(['password'])->toArray();
        $user->update(['password' => Hash::make($password)]);
        AuditTrails::editEventLogger('users', 'Edit', ['password' => '***'], User::$_fillable ?? [], $oldData, $id);

        return true;
    }

    public function findByAccountId(int $id, ?int $accountId = null): ?User
    {
        $accountId ??= Auth::user()->account_id;

        return User::where('id', $id)
            ->where('account_id', $accountId)
            ->first();
    }

    public function searchPatientsOptimized(string $search, int $accountId): mixed
    {
        return Patients::getPatientSearchOptimized($search, $accountId);
    }

    /**
     * SECURITY (privilege-escalation guard): a non-Super-Admin must never be
     * able to ADD the Super-Admin role — its Gate::before bypass grants
     * everything — but must also never STRIP an existing Super-Admin assignment
     * (a routine profile edit must not silently demote a Super-Admin). So the
     * Super-Admin role is frozen to whatever the target already had: kept if
     * present in $existingRoleIds, otherwise removed. This mirrors
     * RoleService::grantablePermissionSet (escalation-proof AND non-breaking).
     * Super-Admin actors and system/console context (no auth) are unrestricted.
     *
     * @param  array<int, int|string>  $roleIds         requested role ids
     * @param  array<int, int|string>  $existingRoleIds the target's current role ids
     * @return array<int, int|string>
     */
    private function assignableRoleIds(array $roleIds, array $existingRoleIds = []): array
    {
        $actor = Auth::user();
        if ($actor === null || $actor->hasRole('Super-Admin')) {
            return array_values($roleIds);
        }

        $superAdminId = (int) (Role::where('name', 'Super-Admin')->value('id') ?? 0);

        // Drop any attempt to ADD Super-Admin...
        $assignable = array_values(array_filter(
            $roleIds,
            static fn ($id): bool => (int) $id !== $superAdminId,
        ));

        // ...but re-add it if the target already had it (freeze — don't demote).
        if ($superAdminId > 0 && in_array($superAdminId, array_map('intval', $existingRoleIds), true)) {
            $assignable[] = $superAdminId;
        }

        return array_values(array_unique($assignable));
    }

    private function syncRoleHasUsers(User $user, array $roles): void
    {
        $existingRoles = Role::whereIn('id', $roles)->pluck('id');

        $records = $existingRoles->map(fn (int $roleId): array => [
            'role_id' => $roleId,
            'user_id' => $user->id,
        ])->all();

        foreach ($records as $record) {
            RoleHasUsers::create($record);
        }
    }

    private function syncUserLocations(User $user, array $centers): void
    {
        $accountId = Auth::user()->account_id;
        $locations = LocationsWidget::generatelocationArray($centers, $accountId, $user->id);

        foreach ($locations as $location) {
            UserHasLocations::create([
                'user_id' => $location['user_id'],
                'region_id' => $location['region_id'],
                'location_id' => $location['location_id'],
            ]);
        }
    }

    private function syncUserWarehouses(User $user, array $warehouseIds): void
    {
        $query = Warehouse::where('active', 1);

        if (! in_array('all', $warehouseIds, true)) {
            $query->whereIn('id', $warehouseIds);
        }

        $query->each(fn (Warehouse $warehouse) => UserHasWarehouse::create([
            'user_id' => $user->id,
            'warehouse_id' => $warehouse->id,
        ]));
    }
}
