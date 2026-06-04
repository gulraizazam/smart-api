<?php

declare(strict_types=1);

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
use App\Services\Phone\PhoneFormattingService;
use App\Services\Reports\Concerns\ParsesDateRange;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DoctorService
{
    use ParsesDateRange;

    private const FILTER_KEY = 'doctors';

    public function getDatatableData(array $params): array
    {
        $user = Auth::user();
        $canViewInactive = Gate::allows('view_inactive_doctors');

        $where = $this->buildWhereConditions($params, $user->id, $user->account_id);

        $baseQuery = User::leftJoin('role_has_users', 'users.id', '=', 'role_has_users.user_id')
            ->where('users.user_type_id', Config::get('constants.practitioner_id'))
            ->where('users.account_id', $user->account_id)
            ->where('users.resource_type_id', 2)
            ->when(! $canViewInactive, fn ($q) => $q->where('users.active', 1));

        foreach ($where as $condition) {
            $baseQuery->where($condition[0], $condition[1], $condition[2]);
        }

        $total = (clone $baseQuery)->distinct()->count('users.id');

        $users = (clone $baseQuery)
            ->select('users.*')
            ->distinct()
            ->orderBy($params['orderBy'] ?? 'users.created_at', $params['order'] ?? 'desc')
            ->offset($params['offset'] ?? 0)
            ->limit($params['limit'] ?? 30)
            ->get();

        return [
            'data' => $this->formatDatatableData($users),
            'total' => $total,
        ];
    }

    private function buildWhereConditions(array $params, int $userId, int $accountId): array
    {
        $where = [];
        $applyFilter = $params['apply_filter'] ?? false;

        $where = $this->addFilter($where, $params, 'name', 'users.name', 'like', $userId, $applyFilter);
        $where = $this->addFilter($where, $params, 'email', 'users.email', 'like', $userId, $applyFilter);

        // Phone filter (needs number cleaning)
        if (! empty($params['phone'])) {
            $where[] = ['users.phone', 'like', '%'.PhoneFormattingService::cleanNumber($params['phone']).'%'];
            Filters::put($userId, self::FILTER_KEY, 'phone', $params['phone']);
        } elseif ($applyFilter) {
            Filters::forget($userId, self::FILTER_KEY, 'phone');
        } elseif ($storedPhone = Filters::get($userId, self::FILTER_KEY, 'phone')) {
            $where[] = ['users.phone', 'like', '%'.PhoneFormattingService::cleanNumber($storedPhone).'%'];
        }

        $where = $this->addFilter($where, $params, 'gender', 'users.gender', '=', $userId, $applyFilter);
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
        } else {
            $storedValue = Filters::get($userId, self::FILTER_KEY, $key);
            if ($storedValue) {
                $value = $operator === 'like' ? "%{$storedValue}%" : $storedValue;
                $where[] = [$column, $operator, $value];
            }
        }

        return $where;
    }

    private function formatDatatableData(mixed $users): array
    {
        return $users->map(fn (User $user): array => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => GeneralFunctions::contactStatus($user->phone),
            'gender' => config('constants.gender_array.'.$user->gender),
            'roles' => $user->user_roles()->pluck('name')->all(),
            'active' => $user->active,
            'status' => $user->active,
            'created_at' => $user->created_at->format('F j,Y h:i A'),
        ])->all();
    }

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

    public function getActiveFilters(): array
    {
        return Filters::all(Auth::user()->id, self::FILTER_KEY);
    }

    public function getCreateData(): array
    {
        $accountId = Auth::user()->account_id;

        $doctor = new \stdClass;
        $doctor->gender = null;
        $doctor->phone = null;

        $userstype = UserTypes::where('account_id', $accountId)
            ->where('type', 'consultant')
            ->pluck('name', 'id');
        $userstype->prepend('Select a User Type', '');

        $locations = Locations::with('city')
            ->where('account_id', $accountId)
            ->where('active', 1)
            ->get()
            ->pluck('full_address', 'id');

        $parentGroups = new NodesTree;
        $parentGroups->current_id = -1;
        $parentGroups->build(0, $accountId, true, true);
        $parentGroups->toList($parentGroups, -1);

        return [
            'locations' => $locations,
            'userstype' => $userstype,
            'user' => $doctor,
            'Services' => $parentGroups->nodeList,
            'DoctorServices' => [],
            'roles' => Role::pluck('name', 'id'),
        ];
    }

    public function getEditData(int $id): ?array
    {
        $accountId = Auth::user()->account_id;

        $doctor = User::where('id', $id)
            ->where('account_id', $accountId)
            ->first();

        if (! $doctor) {
            return null;
        }

        $userstype = UserTypes::where('account_id', $accountId)
            ->where('type', 'consultant')
            ->pluck('name', 'id');
        $userstype->prepend('Select a User Type', '');

        $parentGroups = new NodesTree;
        $parentGroups->current_id = -1;
        $parentGroups->build(0, $accountId, true, true);
        $parentGroups->toList($parentGroups, -1);

        return [
            'user' => $doctor,
            'user_has_locations' => $doctor->user_has_locations->pluck('location_id'),
            'locations' => Locations::with('city')
                ->where('account_id', $accountId)
                ->where('active', 1)
                ->get()
                ->pluck('full_address', 'id'),
            'userstype' => $userstype,
            'DoctorServices' => $doctor->doctor_has_services()->pluck('service_id')->all(),
            'Services' => $parentGroups->nodeList,
            'roles' => Role::pluck('name', 'id'),
            'user_roles' => $doctor->user_roles()->pluck('id'),
        ];
    }

    public function create(array $data): ?User
    {
        $resourcetype = ResourceTypes::where('name', 'doctor')->firstOrFail();

        $data['resource_type_id'] = $resourcetype->id;
        $data['user_type_id'] = Config::get('constants.practitioner_id');
        $data['account_id'] = Auth::user()->account_id;
        $data['phone'] = PhoneFormattingService::cleanNumber($data['phone']);
        $data['can_perform_consultation'] = isset($data['can_perform_consultation']) ? 1 : 0;

        $user = User::create($data);
        AuditTrails::addEventLogger('users', 'create', $data, ['name', 'email', 'phone', 'gender', 'user_type_id', 'resource_type_id', 'account_id', 'active'], $user);

        if (! $user) {
            return null;
        }

        // Assign roles
        $roles = $data['roles'] ?? [];
        if (! empty($roles)) {
            $user->assignRole(Role::whereIn('id', $roles)->get());

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

    public function update(int $id, array $data): ?User
    {
        $user = User::findOrFail($id);

        // Handle masked phone
        if (($data['phone'] ?? null) === '***********' && isset($data['old_phone'])) {
            $data['phone'] = $data['old_phone'];
        }
        unset($data['old_phone']);

        $data['phone'] = PhoneFormattingService::cleanNumber($data['phone']);
        $data['can_perform_consultation'] = isset($data['can_perform_consultation']) ? 1 : 0;

        $oldData = $user->makeVisible(['password'])->toArray();

        $user->update($data);
        AuditTrails::addEventLogger('users', 'update', $oldData, ['name', 'email', 'phone', 'gender', 'user_type_id', 'resource_type_id', 'account_id', 'active'], $user);

        // Sync roles
        $roles = $data['roles'] ?? [];
        $user->syncRoles(Role::whereIn('id', $roles)->get());

        if (! empty($roles) && is_array($roles)) {
            $user->role_has_users()->forceDelete();

            foreach ($roles as $roleId) {
                RoleHasUsers::create([
                    'role_id' => $roleId,
                    'user_id' => $user->id,
                ]);
            }
        }

        // Update or create resource record
        Resources::updateOrCreate(
            ['external_id' => $user->id],
            [
                'name' => $data['name'] ?? $user->name,
                'account_id' => Auth::user()->account_id,
                'resource_type_id' => ResourceTypes::where('name', 'doctor')->value('id'),
                'active' => 1,
            ]
        );

        return $user;
    }

    public function delete(int $id): array
    {
        $accountId = Auth::user()->account_id;

        if (User::isExists($id, $accountId)) {
            return [
                'status' => false,
                'message' => 'Record cannot be deleted because it has related records.',
            ];
        }

        $user = User::find($id);
        if (! $user) {
            return ['status' => false, 'message' => 'Record not found.'];
        }

        $user->delete();

        return ['status' => true, 'message' => 'Record has been deleted successfully.'];
    }

    public function bulkDelete(array $ids): int
    {
        $accountId = Auth::user()->account_id;
        $deleted = 0;

        User::where('account_id', $accountId)->whereIn('id', $ids)->each(function (User $user) use ($accountId, &$deleted): void {
            if (! User::isExists($user->id, $accountId)) {
                $user->delete();
                $deleted++;
            }
        });

        return $deleted;
    }

    public function changeStatus(int $id, int $status): bool
    {
        $user = User::find($id);

        if (! $user) {
            return false;
        }

        $user->update(['active' => $status]);

        return true;
    }

    public function changePassword(int $id, string $password): bool
    {
        $user = User::find($id);

        if (! $user) {
            return false;
        }

        $user->update(['password' => Hash::make($password)]);

        return true;
    }

    public function getPasswordChangeData(int $id): ?User
    {
        return User::where('id', $id)
            ->where('account_id', Auth::user()->account_id)
            ->first();
    }

    public function getLocationAllocationData(int $id): array
    {
        $doctor = User::findOrFail($id);

        return [
            'doctor' => $doctor,
            'location' => LocationsWidget::generateDropDownArray(Auth::user()->account_id),
            'doctor_has_location' => DoctorHasLocations::with(['service', 'location.city'])
                ->where('is_allocated', 1)
                ->where('user_id', $doctor->id)
                ->get(),
        ];
    }

    public function getServicesForLocation(mixed $request): array
    {
        return [
            'services' => ServiceWidget::generateServiceArrayArray($request, Auth::user()->account_id),
            'locaiton_id_1' => $request->id,
        ];
    }

    public function saveServiceAllocation(int $doctorId, string $locationServiceId): array
    {
        [$locationId, $serviceId] = explode(',', $locationServiceId);

        $service = Services::where('id', $serviceId)->firstOrFail();

        // Check if already exists
        $exists = DoctorHasLocations::where('is_allocated', 1)
            ->where('location_id', $locationId)
            ->where('service_id', $serviceId)
            ->where('user_id', $doctorId)
            ->exists();

        if ($exists) {
            return ['status' => false, 'message' => 'Service already exist!'];
        }

        $data = [
            'user_id' => $doctorId,
            'location_id' => $locationId,
            'service_id' => $serviceId,
            'end_node' => $service->end_node,
            'is_allocated' => 1,
        ];

        $existingQuery = DoctorHasLocations::where('is_allocated', 1)
            ->where('location_id', $locationId)
            ->where('user_id', $doctorId);

        $existingRecords = (clone $existingQuery)->with('service')->get();

        $hasServices = 'new';
        foreach ($existingRecords as $record) {
            $hasServices = match (true) {
                $record->service?->slug === 'all' => 'all',
                $service->parent_id === $record->service?->id => 'parent',
                $service->id === $record->service?->parent_id => 'child',
                default => 'equal',
            };
        }

        $record = match ($hasServices) {
            'new', 'equal' => DoctorHasLocations::create($data),
            'child' => $this->replaceChildWithParent($existingQuery, $service, $data),
            default => null,
        };

        if ($hasServices === 'new' || $hasServices === 'equal' || $hasServices === 'child') {
            // Also handle "all" service replacement
            if ($service->slug === 'all' && $hasServices !== 'child') {
                (clone $existingQuery)->delete();
                $record = DoctorHasLocations::create($data);
            }
        }

        if ($record === null || in_array($hasServices, ['all', 'parent'], true)) {
            return ['status' => false, 'message' => 'Parent Service / All Service already exist!'];
        }

        return [
            'status' => true,
            'message' => 'Success',
            'data' => [
                'record' => $record,
                'record_location_name' => $record->location->city->name.'-'.$record->location->name,
                'record_service_name' => $record->service->name,
            ],
        ];
    }

    private function replaceChildWithParent($query, $service, array $data): DoctorHasLocations
    {
        (clone $query)->whereHas('service', fn ($q) => $q->where('parent_id', $service->id))->delete();

        return DoctorHasLocations::create($data);
    }

    public function deleteServiceAllocation(int $id): bool
    {
        $doctorService = DoctorHasLocations::find($id);

        if (! $doctorService) {
            return false;
        }

        $doctorService->update(['is_allocated' => 0]);

        return true;
    }
}
