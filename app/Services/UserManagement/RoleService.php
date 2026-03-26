<?php

namespace App\Services\UserManagement;

use App\Models\Permission;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class RoleService
{
    private const CACHE_TTL = 3600;
    private const CACHE_KEY_PERMISSIONS_MAPPING = 'roles.permissions_mapping';

    public function getDatatableData(array $params): array
    {
        $query = Role::query();

        if (!empty($params['name'])) {
            $query->where('name', 'LIKE', "%{$params['name']}%");
        }

        if (!empty($params['commission']) && is_numeric($params['commission'])) {
            $query->where('commission', $params['commission']);
        }

        $total = $query->count();

        $roles = $query
            ->orderBy($params['orderBy'] ?? 'name', $params['order'] ?? 'asc')
            ->offset($params['offset'] ?? 0)
            ->limit($params['limit'] ?? 30)
            ->get();

        return [
            'data' => $roles,
            'total' => $total,
        ];
    }

    public function getUserPermissions(): array
    {
        return [
            'edit' => Gate::allows('roles_edit'),
            'duplicate' => Gate::allows('roles_duplicate'),
            'delete' => Gate::allows('roles_destroy'),
        ];
    }

    public function getAllPermissionsMapping(): array
    {
        $suffix = Auth::user()->hasRole('Super-Admin') ? 'super' : 'normal';
        $cacheKey = self::CACHE_KEY_PERMISSIONS_MAPPING . '.' . $suffix;

        return Cache::remember($cacheKey, self::CACHE_TTL, fn (): array => $this->buildPermissionsMapping());
    }

    private function buildPermissionsMapping(): array
    {
        $dashboardNames = ['dashboard_manage'];

        $reportsNames = [
            'leads_reports_manage', 'feedbacks_report_manage', 'appointment_reports_manage',
            'operations_reports_manage', 'centers_reports_manage', 'Hr_reports_manage',
            'finance_general_revenue_reports_manage', 'finance_revenue_breakup_reports_manage',
            'finance_ledger_reports_manage', 'staff_listing_reports_manage',
            'staff_revenue_reports_manage', 'marketing_reports_manage', 'conversion_report_manage',
            'staff_wise_arrival_manage', 'non_converted_customers_manage', 'follow_up_manage',
            'followuppatient_manage',
        ];

        $excludeFromGeneral = [...$dashboardNames, ...$reportsNames];

        $notInNamesForNonSuper = [
            'view_inactive_users', 'view_inactive_appointment_statuses', 'view_inactive_centres',
            'view_inactive_cities', 'view_inactive_discounts', 'view_inactive_doctors',
            'view_inactive_lead_sources', 'view_inactive_leads', 'view_inactive_lead_statuses',
            'view_inactive_machine_types', 'view_inactive_packages', 'view_inactive_patients',
            'view_inactive_payment_modes', 'view_inactive_plans', 'view_inactive_products',
            'view_inactive_regions', 'view_inactive_custom_forms', 'view_inactive_towns',
            'view_inactive_resources', 'view_inactive_rota', 'view_inactive_rotas',
            'view_inactive_services', 'view_inactive_sms_templates',
        ];

        $isSuperAdmin = Auth::user()->hasRole('Super-Admin');

        return [
            'permissions' => $this->buildPermissionGroup(
                excludeNames: $excludeFromGeneral,
                additionalExcludes: $isSuperAdmin ? [] : $notInNamesForNonSuper,
                useWhereIn: false,
            ),
            'dashboard_permissions' => $this->buildPermissionGroup(
                filterNames: $dashboardNames,
                useWhereIn: true,
            ),
            'reports_permissions' => $this->buildPermissionGroup(
                filterNames: $reportsNames,
                useWhereIn: true,
            ),
        ];
    }

    private function buildPermissionGroup(
        array $filterNames = [],
        array $excludeNames = [],
        array $additionalExcludes = [],
        bool $useWhereIn = false,
    ): array {
        $query = Permission::where(['main_group' => 1, 'status' => 1]);

        if ($useWhereIn) {
            $query->whereIn('name', $filterNames);
        } else {
            $query->whereNotIn('name', $excludeNames);
            if (!empty($additionalExcludes)) {
                $query->whereNotIn('name', $additionalExcludes);
            }
        }

        $groupPermissions = $query->orderBy('sort_order')->get();
        $parentIds = $groupPermissions->pluck('id')->all();

        $subPermissions = Permission::whereIn('parent_id', $parentIds)
            ->orderBy('sort_order')
            ->get()
            ->groupBy('parent_id');

        $result = [];
        foreach ($groupPermissions as $group) {
            $children = [];
            foreach ($subPermissions->get($group->id, collect()) as $sub) {
                $children[$sub->name] = [
                    'id' => $sub->id,
                    'title' => $sub->title,
                    'name' => $sub->name,
                    'parent_id' => $sub->parent_id,
                ];
            }

            $result[$group->id] = [
                'id' => $group->id,
                'title' => $group->title,
                'name' => $group->name,
                'parent_id' => $group->parent_id,
                'children' => $children,
                'key' => Str::replaceLast('manage', '', $group->name),
            ];
        }

        return $result;
    }

    public function getAllowedPermissions(?int $roleId = null): array
    {
        $query = Permission::join('role_has_permissions', 'role_has_permissions.permission_id', '=', 'permissions.id');

        if ($roleId) {
            $query->where('role_has_permissions.role_id', $roleId);
        }

        return $query->pluck('permissions.name', 'permissions.id')->all() ?: [];
    }

    public function create(array $data): Role
    {
        $permissions = $data['permission'] ?? [];
        unset($data['permission'], $data['DataTables_Table_0_length']);

        $role = Role::create($data);
        $role->givePermissionTo($permissions);

        $this->clearCache();

        return $role;
    }

    public function update(int $id, array $data): Role
    {
        $role = $this->findOrFail($id);

        $permissions = $data['permission'] ?? [];
        unset($data['permission'], $data['DataTables_Table_0_length']);

        $role->update($data);
        $role->syncPermissions($permissions);

        $this->clearCache();

        return $role;
    }

    public function duplicate(array $data): Role
    {
        return $this->create($data);
    }

    public function delete(int $id): bool
    {
        $role = $this->findOrFail($id);

        if ($this->hasUsers($id)) {
            return false;
        }

        $deleted = $role->delete();
        $this->clearCache();

        return $deleted;
    }

    public function hasUsers(int $roleId): bool
    {
        return DB::table('role_has_users')->where('role_id', $roleId)->exists();
    }

    public function findOrFail(int $id): Role
    {
        return Role::findOrFail($id);
    }

    private function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY_PERMISSIONS_MAPPING . '.super');
        Cache::forget(self::CACHE_KEY_PERMISSIONS_MAPPING . '.normal');
    }
}
