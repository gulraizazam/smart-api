<?php

namespace App\Services\UserManagement;

use App\Helpers\Filters;
use App\Helpers\GeneralFunctions;
use App\Helpers\NodesTree;
use App\Helpers\Widgets\LocationsWidget;
use App\Helpers\Widgets\ServiceWidget;
use App\Models\AuditTrails;
use App\Models\DoctorHasLocations;
use App\Models\Locations;
use App\Models\Resources;
use App\Models\ResourceTypes;
use App\Models\RoleHasUsers;
use App\Models\Services;
use App\Models\User;
use App\Models\UserTypes;
use DateTime;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

class DoctorService
{
    private const FILTER_KEY = 'doctors';

    /**
     * Get paginated doctors for datatable
     */
    public function getDatatableData(array $params): array
    {
        $userId = Auth::user()->id;
        $accountId = Auth::user()->account_id;
        $canViewInactive = Gate::allows('view_inactive_doctors');
        
        // Build filters from params and stored filters
        $where = $this->buildWhereConditions($params, $userId, $accountId);
        
        // Base query with joins - doctors have user_type_id = practitioner and resource_type_id = 2
        $baseQuery = User::leftJoin('role_has_users', 'users.id', '=', 'role_has_users.user_id')
            ->where('users.user_type_id', Config::get('constants.practitioner_id'))
            ->where('users.account_id', $accountId)
            ->where('users.resource_type_id', 2)
            ->groupBy('users.id');
        
        if (!$canViewInactive) {
            $baseQuery->where('users.active', 1);
        }
        
        // Apply where conditions
        foreach ($where as $condition) {
            $baseQuery->where($condition[0], $condition[1], $condition[2]);
        }
        
        // Get total count using pluck (groupBy makes count unreliable)
        $allUserIds = (clone $baseQuery)->pluck('users.id');
        $total = $allUserIds->count();
        
        // Get paginated data
        $users = (clone $baseQuery)
            ->select('users.*')
            ->orderBy($params['orderBy'] ?? 'users.created_at', $params['order'] ?? 'desc')
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
     * Build where conditions from params and stored filters
     */
    private function buildWhereConditions(array $params, int $userId, int $accountId): array
    {
        $where = [];
        $applyFilter = $params['apply_filter'] ?? false;
        
        // Name filter
        $where = $this->addFilter($where, $params, 'name', 'users.name', 'like', $userId, $applyFilter);
        
        // Email filter
        $where = $this->addFilter($where, $params, 'email', 'users.email', 'like', $userId, $applyFilter);
        
        // Phone filter
        if (!empty($params['phone'])) {
            $where[] = ['users.phone', 'like', '%' . GeneralFunctions::cleanNumber($params['phone']) . '%'];
            Filters::put($userId, self::FILTER_KEY, 'phone', $params['phone']);
        } elseif ($applyFilter) {
            Filters::forget($userId, self::FILTER_KEY, 'phone');
        } elseif ($storedPhone = Filters::get($userId, self::FILTER_KEY, 'phone')) {
            $where[] = ['users.phone', 'like', '%' . GeneralFunctions::cleanNumber($storedPhone) . '%'];
        }
        
        // Gender filter
        $where = $this->addFilter($where, $params, 'gender', 'users.gender', '=', $userId, $applyFilter);
        
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
            $value = $operator === 'like' ? '%' . $params[$key] . '%' : $params[$key];
            $where[] = [$column, $operator, $value];
            Filters::put($userId, self::FILTER_KEY, $key, $params[$key]);
        } elseif ($applyFilter) {
            Filters::forget($userId, self::FILTER_KEY, $key);
        } else {
            $storedValue = Filters::get($userId, self::FILTER_KEY, $key);
            if ($storedValue) {
                $value = $operator === 'like' ? '%' . $storedValue . '%' : $storedValue;
                $where[] = [$column, $operator, $value];
            }
        }
        
        return $where;
    }

    /**
     * Format data for datatable response
     */
    private function formatDatatableData($users): array
    {
        $data = [];
        foreach ($users as $user) {
            $data[] = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => GeneralFunctions::contactStatus($user->phone),
                'gender' => config('constants.gender_array.' . $user->gender),
                'roles' => $user->user_roles()->pluck('name')->toArray(),
                'active' => $user->active,
                'status' => $user->active,
                'created_at' => $user->created_at->format('F j,Y h:i A'),
            ];
        }
        return $data;
    }

    /**
     * Get user permissions for datatable
     */
    public function getUserPermissions(): array
    {
        return [
            'edit' => Gate::allows('doctors_edit'),
            'change_password' => Gate::allows('doctors_change_password'),
            'active' => Gate::allows('doctors_active'),
            'inactive' => Gate::allows('doctors_inactive'),
            'delete' => Gate::allows('doctors_destroy'),
            'allocate' => Gate::allows('doctors_allocate'),
            'contact' => Gate::allows('contact'),
        ];
    }

    /**
     * Get filter values for datatable
     */
    public function getFilterValues(): array
    {
        $roles = Role::pluck('name', 'id');
        $roles->prepend('All', '');
        
        return [
            'roles' => $roles,
            'gender_array' => config('constants.gender_array'),
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
     * Get data for creating a new doctor
     */
    public function getCreateData(): array
    {
        $accountId = Auth::user()->account_id;
        
        $doctor = new \stdClass();
        $doctor->gender = null;
        $doctor->phone = null;
        
        $userstype = UserTypes::where('account_id', $accountId)
            ->where('type', 'consultant')
            ->pluck('name', 'id');
        $userstype->prepend('Select a User Type', '');
        
        $locations = Locations::with('city')
            ->where([
                ['account_id', '=', $accountId],
                ['active', '=', '1'],
            ])->get()->pluck('full_address', 'id');
        
        // Build service tree
        $parentGroups = new NodesTree();
        $parentGroups->current_id = -1;
        $parentGroups->build(0, $accountId, true, true);
        $parentGroups->toList($parentGroups, -1);
        $Services = $parentGroups->nodeList;
        
        $roles = Role::pluck('name', 'id');
        
        return [
            'locations' => $locations,
            'userstype' => $userstype,
            'user' => $doctor,
            'Services' => $Services,
            'DoctorServices' => [],
            'roles' => $roles,
        ];
    }

    /**
     * Get data for editing a doctor
     */
    public function getEditData(int $id): ?array
    {
        $accountId = Auth::user()->account_id;
        
        $doctor = User::where([
            ['id', '=', $id],
            ['account_id', '=', $accountId],
        ])->first();
        
        if (!$doctor) {
            return null;
        }
        
        $userstype = UserTypes::where('account_id', $accountId)
            ->where('type', 'consultant')
            ->pluck('name', 'id');
        $userstype->prepend('Select a User Type', '');
        
        $user_has_locations = $doctor->user_has_locations->pluck('location_id');
        $DoctorServices = $doctor->doctor_has_services()->pluck('service_id')->toArray();
        
        // Build service tree
        $parentGroups = new NodesTree();
        $parentGroups->current_id = -1;
        $parentGroups->build(0, $accountId, true, true);
        $parentGroups->toList($parentGroups, -1);
        $Services = $parentGroups->nodeList;
        
        $locations = Locations::with('city')
            ->where([
                ['account_id', '=', $accountId],
                ['active', '=', '1'],
            ])->get()->pluck('full_address', 'id');
        
        $roles = Role::pluck('name', 'id');
        $user_roles = $doctor->user_roles()->pluck('id');
        
        return [
            'user' => $doctor,
            'user_has_locations' => $user_has_locations,
            'locations' => $locations,
            'userstype' => $userstype,
            'DoctorServices' => $DoctorServices,
            'Services' => $Services,
            'roles' => $roles,
            'user_roles' => $user_roles,
        ];
    }

    /**
     * Create a new doctor
     */
    public function create(array $data): ?User
    {
        $resourcetype = ResourceTypes::where('name', '=', 'doctor')->first();
        
        $data['resource_type_id'] = $resourcetype->id;
        $data['user_type_id'] = Config::get('constants.practitioner_id');
        $data['account_id'] = Auth::user()->account_id;
        $data['phone'] = GeneralFunctions::cleanNumber($data['phone']);
        $data['can_perform_consultation'] = isset($data['can_perform_consultation']) ? 1 : 0;
        
        $user = User::create($data);
        AuditTrails::addEventLogger('users', 'create', $data, ['name', 'email', 'password', 'phone', 'gender', 'user_type_id', 'resource_type_id', 'account_id', 'active'], $user);
        
        if ($user) {
            // Assign roles
            $roles = $data['roles'] ?? [];
            $user->assignRole($roles);
            
            // Create role_has_users records
            if (!empty($roles) && is_array($roles)) {
                foreach ($roles as $roleId) {
                    RoleHasUsers::create([
                        'role_id' => $roleId,
                        'user_id' => $user->id,
                    ]);
                }
            }
            
            // Create resource record
            Resources::create([
                'name' => $data['name'],
                'account_id' => Auth::user()->account_id,
                'resource_type_id' => $resourcetype->id,
                'external_id' => $user->id,
                'active' => 1,
            ]);
            
            return $user;
        }
        
        return null;
    }

    /**
     * Update a doctor
     */
    public function update(int $id, array $data): ?User
    {
        $user = User::findOrFail($id);
        
        // Handle masked phone
        if (isset($data['phone']) && $data['phone'] === '***********' && isset($data['old_phone'])) {
            $data['phone'] = $data['old_phone'];
        }
        unset($data['old_phone']);
        
        $data['phone'] = GeneralFunctions::cleanNumber($data['phone']);
        $data['can_perform_consultation'] = isset($data['can_perform_consultation']) ? 1 : 0;
        
        // Store old data for audit
        $oldData = $user->makeVisible(['password'])->toArray();
        
        $user->update($data);
        AuditTrails::addEventLogger('users', 'update', $oldData, ['name', 'email', 'password', 'phone', 'gender', 'user_type_id', 'resource_type_id', 'account_id', 'active'], $user);
        
        // Sync roles
        $roles = $data['roles'] ?? [];
        $user->syncRoles($roles);
        
        // Update role_has_users records
        if (!empty($roles) && is_array($roles)) {
            $user->role_has_users()->forceDelete();
            
            foreach ($roles as $roleId) {
                RoleHasUsers::create([
                    'role_id' => $roleId,
                    'user_id' => $user->id,
                ]);
            }
        }
        
        // Update or create resource record
        $resource = Resources::where('external_id', '=', $user->id)->first();
        if ($resource) {
            $resource->name = $data['name'];
            $resource->save();
        } else {
            $resourcetype = ResourceTypes::where('name', '=', 'doctor')->first();
            Resources::create([
                'name' => $user->name ?? '',
                'account_id' => Auth::user()->account_id ?? '',
                'resource_type_id' => $resourcetype->id ?? '',
                'external_id' => $user->id ?? '',
                'active' => 1,
            ]);
        }
        
        return $user;
    }

    /**
     * Delete a doctor
     */
    public function delete(int $id): array
    {
        $accountId = Auth::user()->account_id;
        
        // Check if child records exist
        if (User::isExists($id, $accountId)) {
            return [
                'status' => false,
                'message' => 'Record cannot be deleted because it has related records.',
            ];
        }
        
        $user = User::find($id);
        if ($user) {
            $user->delete();
            return [
                'status' => true,
                'message' => 'Record has been deleted successfully.',
            ];
        }
        
        return [
            'status' => false,
            'message' => 'Record not found.',
        ];
    }

    /**
     * Bulk delete doctors
     */
    public function bulkDelete(array $ids): int
    {
        $accountId = Auth::user()->account_id;
        $deleted = 0;
        
        $users = User::whereIn('id', $ids)->get();
        foreach ($users as $user) {
            if (!User::isExists($user->id, $accountId)) {
                $user->delete();
                $deleted++;
            }
        }
        
        return $deleted;
    }

    /**
     * Change doctor status
     */
    public function changeStatus(int $id, int $status): bool
    {
        $user = User::find($id);
        if ($user) {
            $user->active = $status;
            $user->save();
            return true;
        }
        return false;
    }

    /**
     * Change doctor password
     */
    public function changePassword(int $id, string $password): bool
    {
        $user = User::find($id);
        if ($user) {
            $user->password = bcrypt($password);
            $user->save();
            return true;
        }
        return false;
    }

    /**
     * Get doctor data for password change
     */
    public function getPasswordChangeData(int $id): ?User
    {
        return User::where([
            ['id', '=', $id],
            ['account_id', '=', Auth::user()->account_id],
        ])->first();
    }

    /**
     * Get location allocation data for a doctor
     */
    public function getLocationAllocationData(int $id): array
    {
        $doctor = User::find($id);
        $location = LocationsWidget::generateDropDownArray(Auth::user()->account_id);
        $doctor_has_location = DoctorHasLocations::with(['service', 'location.city'])
            ->where('is_allocated', 1)
            ->where('user_id', '=', $doctor->id)
            ->get();
        
        return [
            'doctor' => $doctor,
            'location' => $location,
            'doctor_has_location' => $doctor_has_location,
        ];
    }

    /**
     * Get services for a location
     */
    public function getServicesForLocation($request): array
    {
        $services = ServiceWidget::generateServiceArrayArray($request, Auth::user()->account_id);
        
        return [
            'services' => $services,
            'locaiton_id_1' => $request->id,
        ];
    }

    /**
     * Save service allocation for doctor
     */
    public function saveServiceAllocation(int $doctorId, string $locationServiceId): array
    {
        $myArray = explode(',', $locationServiceId);
        $locationId = $myArray[0];
        $serviceId = $myArray[1];
        
        $service = Services::where(['id' => $serviceId])->first();
        
        $data = [
            'user_id' => $doctorId,
            'location_id' => $locationId,
            'service_id' => $serviceId,
            'end_node' => $service->end_node,
            'is_allocated' => 1,
        ];
        
        // Check if service already exists
        $checkedService = DoctorHasLocations::where('is_allocated', 1)
            ->where([
                'location_id' => $locationId,
                'service_id' => $serviceId,
                'user_id' => $doctorId,
            ])->count();
        
        if ($checkedService > 0) {
            return [
                'status' => false,
                'message' => 'Service already exist!',
            ];
        }
        
        $query = DoctorHasLocations::where('is_allocated', 1)
            ->where([
                'location_id' => $locationId,
                'user_id' => $doctorId,
            ]);
        
        $checked = $query->with('service')->get();
        $hasServices = 'new';
        
        if (count($checked) > 0) {
            foreach ($checked->toArray() as $value) {
                if ($value['service']['slug'] == 'all') {
                    $hasServices = 'all';
                } elseif ($service->parent_id == $value['service']['id']) {
                    $hasServices = 'parent';
                } elseif ($service->id == $value['service']['parent_id']) {
                    $hasServices = 'child';
                } else {
                    $hasServices = 'equal';
                }
            }
        }
        
        $record = null;
        
        if ($hasServices == 'new') {
            $record = DoctorHasLocations::create($data);
        } elseif ($service->slug == 'all') {
            $query->delete();
            $record = DoctorHasLocations::create($data);
        } elseif ($hasServices == 'child') {
            $query->whereHas('service', fn ($q) => $q->where(['parent_id' => $service->id]))->delete();
            $record = DoctorHasLocations::create($data);
        } elseif ($hasServices == 'equal') {
            $record = DoctorHasLocations::create($data);
        } elseif ($hasServices == 'all' || $hasServices == 'parent') {
            return [
                'status' => false,
                'message' => 'Parent Service / All Service already exist!',
            ];
        } else {
            return [
                'status' => false,
                'message' => 'Service not found!',
            ];
        }
        
        return [
            'status' => true,
            'message' => 'Success',
            'data' => [
                'record' => $record,
                'record_location_name' => $record->location->city->name . '-' . $record->location->name,
                'record_service_name' => $record->service->name,
            ],
        ];
    }

    /**
     * Delete service allocation
     */
    public function deleteServiceAllocation(int $id): bool
    {
        $doctorService = DoctorHasLocations::find($id);
        if ($doctorService) {
            $doctorService->update(['is_allocated' => 0]);
            return true;
        }
        return false;
    }
}
