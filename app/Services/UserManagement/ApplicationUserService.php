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
use DateTime;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

class ApplicationUserService
{
    private const FILTER_KEY = 'users';

    /**
     * Get paginated users for datatable
     */
    public function getDatatableData(array $params): array
    {
        $userId = Auth::user()->id;
        $accountId = Auth::user()->account_id;
        $canViewInactive = Gate::allows('view_inactive_users');
        
        // Build filters from params and stored filters
        $where = $this->buildWhereConditions($params, $userId, $accountId);
        
        // Base query with joins
        $baseQuery = User::leftJoin('user_has_locations', 'users.id', '=', 'user_has_locations.user_id')
            ->leftJoin('role_has_users', 'users.id', '=', 'role_has_users.user_id')
            ->whereNotIn('users.user_type_id', [
                Config::get('constants.practitioner_id'),
                Config::get('constants.patient_id')
            ])
            ->where('users.email', '!=', 'superadmin@redsignal.net')
            ->where('users.account_id', $accountId)
            ->groupBy('users.id');
        
        if (!$canViewInactive) {
            $baseQuery->where('users.active', 1);
        }
        
        // Apply where conditions
        foreach ($where as $condition) {
            $baseQuery->where($condition[0], $condition[1], $condition[2]);
        }
        
        // Get all user IDs first for count (groupBy makes count unreliable)
        $allUserIds = (clone $baseQuery)->pluck('users.id');
        $total = $allUserIds->count();
        
        // Get paginated data
        $users = (clone $baseQuery)
            ->select('users.*')
            ->orderBy($params['orderBy'] ?? 'users.name', $params['order'] ?? 'asc')
            ->offset($params['offset'] ?? 0)
            ->limit($params['limit'] ?? 30)
            ->get();

        // Format data for datatable
        $data = $this->formatDatatableData($users);

        return [
            'data' => $data,
            'total' => $total,
        ];
    }

    /**
     * Build where conditions from params
     */
    private function buildWhereConditions(array $params, int $userId, ?int $accountId): array
    {
        $where = [];
        $applyFilter = $params['apply_filter'] ?? false;

        // Name filter
        $where = $this->addFilter($where, $params, 'name', 'users.name', 'like', $userId, $applyFilter);
        
        // Email filter
        $where = $this->addFilter($where, $params, 'email', 'users.email', 'like', $userId, $applyFilter);
        
        // Phone filter
        if (!empty($params['phone'])) {
            $phone = GeneralFunctions::cleanNumber($params['phone']);
            $where[] = ['users.phone', 'like', "%{$phone}%"];
            Filters::put($userId, self::FILTER_KEY, 'phone', $params['phone']);
        } elseif ($applyFilter) {
            Filters::forget($userId, self::FILTER_KEY, 'phone');
        } elseif ($storedPhone = Filters::get($userId, self::FILTER_KEY, 'phone')) {
            $where[] = ['users.phone', 'like', '%' . GeneralFunctions::cleanNumber($storedPhone) . '%'];
        }
        
        // Gender filter
        $where = $this->addFilter($where, $params, 'gender', 'users.gender', '=', $userId, $applyFilter);
        
        // Commission filter
        $where = $this->addFilter($where, $params, 'commission', 'users.commission', '=', $userId, $applyFilter);
        
        // Location filter
        $where = $this->addFilter($where, $params, 'location_id', 'user_has_locations.location_id', '=', $userId, $applyFilter);
        
        // Role filter
        $where = $this->addFilter($where, $params, 'role_id', 'role_has_users.role_id', '=', $userId, $applyFilter);
        
        // Status filter - handle "0" as valid value for inactive
        if (isset($params['status']) && $params['status'] !== '' && $params['status'] !== null) {
            $statusValue = (int) $params['status'];
            $where[] = ['users.active', '=', $statusValue];
            Filters::put($userId, self::FILTER_KEY, 'status', $statusValue);
        } elseif ($applyFilter) {
            Filters::forget($userId, self::FILTER_KEY, 'status');
        } else {
            $storedStatus = Filters::get($userId, self::FILTER_KEY, 'status');
            if ($storedStatus !== null && $storedStatus !== '' && ($storedStatus === 0 || $storedStatus === 1 || $storedStatus === '0' || $storedStatus === '1')) {
                $where[] = ['users.active', '=', (int) $storedStatus];
            }
        }
        
        // Date range filter
        if (!empty($params['created_at'])) {
            $dateRange = explode(' - ', $params['created_at']);
            $startDate = date('Y-m-d H:i:s', strtotime($dateRange[0]));
            $endDateObj = new DateTime($dateRange[1]);
            $endDateObj->setTime(23, 59, 59);
            $endDate = $endDateObj->format('Y-m-d H:i:s');
            
            $where[] = ['users.created_at', '>=', $startDate];
            $where[] = ['users.created_at', '<=', $endDate];
            Filters::put($userId, self::FILTER_KEY, 'created_at', $params['created_at']);
        } elseif ($applyFilter) {
            Filters::forget($userId, self::FILTER_KEY, 'created_at');
        }

        return $where;
    }

    /**
     * Add a filter condition
     */
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

    /**
     * Format users data for datatable
     */
    private function formatDatatableData($users): array
    {
        $data = [];
        $locations = Locations::select('*')->get()->getDictionary();
        $accountId = Auth::user()->account_id;
        
        foreach ($users as $user) {
            $userLocations = [];
            $userHasLocations = $user->user_has_locations ? $user->user_has_locations->pluck('location_id') : [];
            $locationIds = LocationsWidget::generatelocationArrayEdit($userHasLocations, $accountId, $user);
            
            if ($locationIds) {
                foreach ($locationIds as $locationId) {
                    if (isset($locations[$locationId])) {
                        $userLocations[] = $locations[$locationId]->name ?? '';
                    }
                }
            }
            
            $data[] = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => GeneralFunctions::contactStatus($user->phone),
                'commission' => $user->commission . '%',
                'gender' => view('admin.users.genderselection', compact('user'))->render(),
                'locations' => $userLocations,
                'roles' => $user->user_roles()->pluck('name'),
                'created_at' => Carbon::parse($user->created_at)->format('F j,Y h:i A'),
                'active' => $user->active,
            ];
        }
        
        return $data;
    }

    /**
     * Get user permissions for datatable actions
     */
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

    /**
     * Get filter values for datatable
     */
    public function getFilterValues(): array
    {
        $accountId = Auth::user()->account_id;
        
        $locations = Locations::with('city')
            ->where([
                ['active', '=', '1'],
                ['account_id', '=', $accountId]
            ])->get()
            ->pluck('full_address', 'id');
        
        return [
            'roles' => Role::pluck('name', 'id'),
            'locations' => $locations,
            'status' => config('constants.status'),
        ];
    }

    /**
     * Get active filters
     */
    public function getActiveFilters(): array
    {
        return Filters::all(Auth::user()->id, self::FILTER_KEY);
    }

    /**
     * Get data for creating a new user
     */
    public function getCreateData(): array
    {
        $accountId = Auth::user()->account_id;
        
        $user = new \stdClass();
        $user->gender = null;
        $user->phone = null;
        
        return [
            'roles' => Role::where('name', '!=', 'Super-Admin')->get(),
            'roles_commissions' => Role::where('name', '!=', 'Super-Admin')->get(),
            'locations' => LocationsWidget::generateDropDownArray($accountId),
            'warehouse' => Warehouse::where(['active' => 1])->get(),
            'user' => $user,
        ];
    }

    /**
     * Create a new user
     */
    public function create(array $data): User
    {
        $accountId = Auth::user()->account_id;
        
        $userData = [
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'phone' => GeneralFunctions::cleanNumber($data['phone'] ?? ''),
            'gender' => $data['gender'] ?? null,
            'commission' => $data['commission'] ?? 0,
            'account_id' => $accountId,
            'main_account' => '0',
            'user_type_id' => Config::get('constants.application_user_id'),
        ];
        
        $user = User::create($userData);
        AuditTrails::addEventLogger('users', 'create', $userData, User::$_fillable ?? [], $user);
        
        // Assign roles
        $roles = $data['roles'] ?? [];
        if (!empty($roles)) {
            $user->assignRole($roles);
            $this->syncRoleHasUsers($user, $roles);
        }
        
        // Assign locations
        if (!empty($data['centers'])) {
            $this->syncUserLocations($user, $data['centers']);
        }
        
        // Assign warehouses
        if (!empty($data['warehouse'])) {
            $this->syncUserWarehouses($user, $data['warehouse']);
        }
        
        return $user;
    }

    /**
     * Get data for editing a user
     */
    public function getEditData(int $id): array
    {
        $accountId = Auth::user()->account_id;
        $user = $this->findByAccountId($id, $accountId);
        
        $userHasLocations = $user->user_has_locations->pluck('location_id');
        $userHasLocations = LocationsWidget::generatelocationArrayEdit($userHasLocations, $accountId, $user) ?: [];
        
        $userHasWarehouse = $user->user_has_warehouse->pluck('warehouse_id');
        $userHasWarehouse = $userHasWarehouse->isEmpty() ? [] : $userHasWarehouse->toArray();
        
        $userRoles = $user->user_roles()->pluck('id');
        $userRoles = $userRoles ? $userRoles->toArray() : [];
        
        return [
            'roles' => Role::where('name', '!=', 'Super-Admin')->pluck('name', 'id'),
            'roles_commissions' => Role::where('name', '!=', 'Super-Admin')->get(),
            'user' => $user,
            'locations' => LocationsWidget::generateDropDownArray($accountId),
            'warehouse' => Warehouse::where(['active' => 1])->get(),
            'user_has_locations' => $userHasLocations,
            'user_has_warehouse' => $userHasWarehouse,
            'user_roles' => $userRoles,
        ];
    }

    /**
     * Update a user
     */
    public function update(int $id, array $data): User
    {
        $accountId = Auth::user()->account_id;
        $user = $this->findByAccountId($id, $accountId);
        
        $oldData = $user->makeVisible(['password'])->toArray();
        
        // Handle phone masking
        if (isset($data['phone']) && $data['phone'] === '***********' && isset($data['old_phone'])) {
            $data['phone'] = $data['old_phone'];
        }
        unset($data['old_phone']);
        
        $userData = [
            'name' => $data['name'],
            'phone' => GeneralFunctions::cleanNumber($data['phone'] ?? ''),
            'gender' => $data['gender'] ?? null,
            'commission' => $data['commission'] ?? $user->commission,
        ];
        
        $user->update($userData);
        AuditTrails::editEventLogger('users', 'Edit', $userData, User::$_fillable ?? [], $oldData, $id);
        
        // Sync roles
        $roles = $data['roles'] ?? [];
        if (!empty($roles)) {
            $user->syncRoles($roles);
            $user->role_has_users()->forceDelete();
            $this->syncRoleHasUsers($user, $roles);
        }
        
        // Sync locations
        if (!empty($data['centers'])) {
            $user->user_has_locations()->forceDelete();
            $this->syncUserLocations($user, $data['centers']);
        }
        
        // Sync warehouses
        $user->user_has_warehouse()->delete();
        if (!empty($data['warehouse'])) {
            $this->syncUserWarehouses($user, $data['warehouse']);
        }
        
        return $user;
    }

    /**
     * Delete a user
     */
    public function delete(int $id): bool
    {
        $accountId = Auth::user()->account_id;
        $user = $this->findByAccountId($id, $accountId);
        
        if (!$user) {
            return false;
        }
        
        $deleted = $user->delete();
        AuditTrails::deleteEventLogger('users', 'delete', User::$_fillable ?? [], $id);
        
        return $deleted;
    }

    /**
     * Bulk delete users
     */
    public function bulkDelete(array $ids): int
    {
        $accountId = Auth::user()->account_id;
        
        $users = User::where('account_id', $accountId)
            ->whereIn('id', $ids)
            ->get();
        
        $deleted = 0;
        foreach ($users as $user) {
            if ($user->delete()) {
                AuditTrails::deleteEventLogger('users', 'delete', User::$_fillable ?? [], $user->id);
                $deleted++;
            }
        }
        
        return $deleted;
    }

    /**
     * Change user status (active/inactive)
     */
    public function changeStatus(int $id, int $status): bool
    {
        $accountId = Auth::user()->account_id;
        $user = $this->findByAccountId($id, $accountId);
        
        if (!$user) {
            return false;
        }
        
        $user->update(['active' => $status]);
        
        if ($status) {
            AuditTrails::activeEventLogger('users', 'active', User::$_fillable ?? [], $id);
        } else {
            AuditTrails::InactiveEventLogger('users', 'inactive', User::$_fillable ?? [], $id);
        }
        
        return true;
    }

    /**
     * Change user password
     */
    public function changePassword(int $id, string $password): bool
    {
        $accountId = Auth::user()->account_id;
        $user = $this->findByAccountId($id, $accountId);
        
        if (!$user) {
            return false;
        }
        
        $oldData = $user->makeVisible(['password'])->toArray();
        $user->update(['password' => bcrypt($password)]);
        AuditTrails::editEventLogger('users', 'Edit', ['password' => '***'], User::$_fillable ?? [], $oldData, $id);
        
        return true;
    }

    /**
     * Find user by ID and account ID
     */
    public function findByAccountId(int $id, ?int $accountId = null): ?User
    {
        $accountId = $accountId ?? Auth::user()->account_id;
        
        return User::where([
            ['id', '=', $id],
            ['account_id', '=', $accountId],
        ])->first();
    }

    /**
     * Sync role_has_users table
     */
    private function syncRoleHasUsers(User $user, array $roles): void
    {
        foreach ($roles as $roleId) {
            $role = DB::table('roles')->select('id')->where('id', '=', $roleId)->first();
            if ($role) {
                RoleHasUsers::create([
                    'role_id' => $role->id,
                    'user_id' => $user->id,
                ]);
            }
        }
    }

    /**
     * Sync user locations
     */
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

    /**
     * Sync user warehouses
     */
    private function syncUserWarehouses(User $user, array $warehouseIds): void
    {
        if (in_array('all', $warehouseIds)) {
            $warehouses = Warehouse::where(['active' => 1])->get();
        } else {
            $warehouses = Warehouse::where(['active' => 1])->whereIn('id', $warehouseIds)->get();
        }
        
        foreach ($warehouses as $warehouse) {
            UserHasWarehouse::create([
                'user_id' => $user->id,
                'warehouse_id' => $warehouse->id,
            ]);
        }
    }
}
