<?php

namespace App\Services\UserManagement;

use App\Helpers\Filters;
use App\Helpers\GeneralFunctions;
use App\Helpers\Widgets\LocationsWidget;
use App\Models\AuditTrails;
use App\Models\Locations;
use App\Models\RoleHasUsers;
use App\Models\User;
use App\Models\UserHasLocations;
use App\Models\UserHasWarehouse;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class ApplicationUserService
{
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
            ->where('users.email', '!=', 'superadmin@redsignal.net')
            ->where('users.account_id', $user->account_id)
            ->when(!$canViewInactive, fn ($q) => $q->where('users.active', 1))
            ->groupBy('users.id');

        foreach ($where as $condition) {
            $baseQuery->where($condition[0], $condition[1], $condition[2]);
        }

        $total = (clone $baseQuery)->pluck('users.id')->count();

        $users = (clone $baseQuery)
            ->select('users.*')
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

        // Phone filter (needs number cleaning)
        if (!empty($params['phone'])) {
            $phone = GeneralFunctions::cleanNumber($params['phone']);
            $where[] = ['users.phone', 'like', "%{$phone}%"];
            Filters::put($userId, self::FILTER_KEY, 'phone', $params['phone']);
        } elseif ($applyFilter) {
            Filters::forget($userId, self::FILTER_KEY, 'phone');
        } elseif ($storedPhone = Filters::get($userId, self::FILTER_KEY, 'phone')) {
            $where[] = ['users.phone', 'like', '%' . GeneralFunctions::cleanNumber($storedPhone) . '%'];
        }

        $where = $this->addFilter($where, $params, 'gender', 'users.gender', '=', $userId, $applyFilter);

        $where = $this->addFilter($where, $params, 'location_id', 'user_has_locations.location_id', '=', $userId, $applyFilter);
        $where = $this->addFilter($where, $params, 'role_id', 'role_has_users.role_id', '=', $userId, $applyFilter);

        // Status filter - handle "0" as valid value
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

        // Date range filter
        if (!empty($params['created_at'])) {
            $dateRange = explode(' - ', $params['created_at']);
            $where[] = ['users.created_at', '>=', Carbon::parse($dateRange[0])->startOfDay()];
            $where[] = ['users.created_at', '<=', Carbon::parse($dateRange[1])->endOfDay()];
            Filters::put($userId, self::FILTER_KEY, 'created_at', $params['created_at']);
        } elseif ($applyFilter) {
            Filters::forget($userId, self::FILTER_KEY, 'created_at');
        }

        return $where;
    }

    private function addFilter(array $where, array $params, string $key, string $column, string $operator, int $userId, bool $applyFilter): array
    {
        if (!empty($params[$key])) {
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

    private function formatDatatableData($users): array
    {
        $locations = Locations::all()->getDictionary();
        $accountId = Auth::user()->account_id;

        return $users->map(function (User $user) use ($locations, $accountId): array {
            $userHasLocations = $user->user_has_locations?->pluck('location_id') ?? collect();
            $locationIds = LocationsWidget::generatelocationArrayEdit($userHasLocations, $accountId, $user);

            $userLocations = collect($locationIds ?? [])
                ->filter(fn ($id) => isset($locations[$id]))
                ->map(fn ($id) => $locations[$id]->name ?? '')
                ->values()
                ->all();

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => GeneralFunctions::contactStatus($user->phone),

                'gender' => view('admin.users.genderselection', compact('user'))->render(),
                'locations' => $userLocations,
                'roles' => $user->user_roles()->pluck('name'),
                'created_at' => Carbon::parse($user->created_at)->format('F j,Y h:i A'),
                'active' => $user->active,
            ];
        })->all();
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

        $user = new \stdClass();
        $user->gender = null;
        $user->phone = null;

        return [
            'roles' => Role::where('name', '!=', 'Super-Admin')->get(),
            'locations' => LocationsWidget::generateDropDownArray($accountId),
            'warehouse' => Warehouse::where('active', 1)->get(),
            'user' => $user,
        ];
    }

    public function create(array $data): User
    {
        $accountId = Auth::user()->account_id;

        $userData = [
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'phone' => GeneralFunctions::cleanNumber($data['phone'] ?? ''),
            'gender' => $data['gender'] ?? null,

            'account_id' => $accountId,
            'main_account' => '0',
            'user_type_id' => Config::get('constants.application_user_id'),
        ];

        $user = User::create($userData);
        AuditTrails::addEventLogger('users', 'create', $userData, User::$_fillable ?? [], $user);

        $roles = $data['roles'] ?? [];
        if (!empty($roles)) {
            $user->assignRole(Role::whereIn('id', $roles)->get());
            $this->syncRoleHasUsers($user, $roles);
        }

        if (!empty($data['centers'])) {
            $this->syncUserLocations($user, $data['centers']);
        }

        if (!empty($data['warehouse'])) {
            $this->syncUserWarehouses($user, $data['warehouse']);
        }

        return $user;
    }

    public function getEditData(int $id): array
    {
        $accountId = Auth::user()->account_id;
        $user = $this->findByAccountId($id, $accountId);

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

        // Handle masked phone
        if (($data['phone'] ?? null) === '***********' && isset($data['old_phone'])) {
            $data['phone'] = $data['old_phone'];
        }
        unset($data['old_phone']);

        $userData = [
            'name' => $data['name'],
            'phone' => GeneralFunctions::cleanNumber($data['phone'] ?? ''),
            'gender' => $data['gender'] ?? null,

        ];

        $user->update($userData);
        AuditTrails::editEventLogger('users', 'Edit', $userData, User::$_fillable ?? [], $oldData, $id);

        $roles = $data['roles'] ?? [];
        if (!empty($roles)) {
            $user->syncRoles(Role::whereIn('id', $roles)->get());
            $user->role_has_users()->forceDelete();
            $this->syncRoleHasUsers($user, $roles);
        }

        if (!empty($data['centers'])) {
            $user->user_has_locations()->forceDelete();
            $this->syncUserLocations($user, $data['centers']);
        }

        $user->user_has_warehouse()->delete();
        if (!empty($data['warehouse'])) {
            $this->syncUserWarehouses($user, $data['warehouse']);
        }

        return $user;
    }

    public function delete(int $id): bool
    {
        $user = $this->findByAccountId($id);

        if (!$user) {
            return false;
        }

        $deleted = $user->delete();
        AuditTrails::deleteEventLogger('users', 'delete', User::$_fillable ?? [], $id);

        return $deleted;
    }

    public function changeStatus(int $id, int $status): bool
    {
        $user = $this->findByAccountId($id);

        if (!$user) {
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

        if (!$user) {
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

        if (!in_array('all', $warehouseIds)) {
            $query->whereIn('id', $warehouseIds);
        }

        $query->each(fn (Warehouse $warehouse) => UserHasWarehouse::create([
            'user_id' => $user->id,
            'warehouse_id' => $warehouse->id,
        ]));
    }
}
