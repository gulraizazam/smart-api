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
    private const CACHE_TTL = 3600; // 1 hour
    private const CACHE_KEY_PERMISSIONS_MAPPING = 'roles.permissions_mapping';

    /**
     * Get paginated roles for datatable
     */
    public function getDatatableData(array $params): array
    {
        $query = Role::query();
        
        $totalBeforeFilter = Role::count();
        
        if (!empty($params['name'])) {
            $query->where('name', 'LIKE', "%{$params['name']}%");
        }
        
        if (!empty($params['commission']) && is_numeric($params['commission'])) {
            $query->where('commission', $params['commission']);
        }
        
        $totalFiltered = $query->count();
        
        $orderBy = $params['orderBy'] ?? 'name';
        $order = $params['order'] ?? 'asc';
        
        $roles = $query
            ->orderBy($orderBy, $order)
            ->offset($params['offset'] ?? 0)
            ->limit($params['limit'] ?? 30)
            ->get();

        return [
            'data' => $roles,
            'total' => $totalFiltered,
            'totalBeforeFilter' => $totalBeforeFilter,
        ];
    }

    /**
     * Get user permissions for datatable actions
     */
    public function getUserPermissions(): array
    {
        return [
            'edit' => Gate::allows('roles_edit'),
            'duplicate' => Gate::allows('roles_duplicate'),
            'delete' => Gate::allows('roles_destroy'),
        ];
    }

    /**
     * Get all permissions mapping for role create/edit forms
     */
    public function getAllPermissionsMapping(): array
    {
        $cacheKey = self::CACHE_KEY_PERMISSIONS_MAPPING . '.' . (Auth::user()->hasRole('Super-Admin') ? 'super' : 'normal');
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () {
            return $this->buildPermissionsMapping();
        });
    }

    /**
     * Build permissions mapping structure
     */
    private function buildPermissionsMapping(): array
    {
        $notInArray = [
            'dashboard_manage', 'leads_reports_manage', 'feedbacks_report_manage', 
            'appointment_reports_manage', 'operations_reports_manage', 'centers_reports_manage', 
            'Hr_reports_manage', 'finance_general_revenue_reports_manage', 
            'finance_revenue_breakup_reports_manage', 'finance_ledger_reports_manage', 
            'staff_listing_reports_manage', 'staff_revenue_reports_manage', 
            'marketing_reports_manage', 'conversion_report_manage', 'staff_wise_arrival_manage', 
            'non_converted_customers_manage', 'follow_up_manage', 'followuppatient_manage'
        ];
        
        $notInNamesArray = [
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

        // General permissions
        $permissions = $this->buildPermissionGroup($notInArray, $notInNamesArray, $isSuperAdmin, false);
        
        // Dashboard permissions
        $dashboardWhereIn = ['dashboard_manage'];
        $dashboard_permissions = $this->buildPermissionGroup($dashboardWhereIn, [], $isSuperAdmin, true);
        
        // Reports permissions
        $reportsWhereIn = [
            'leads_reports_manage', 'feedbacks_report_manage', 'appointment_reports_manage',
            'operations_reports_manage', 'centers_reports_manage', 'Hr_reports_manage',
            'finance_general_revenue_reports_manage', 'finance_revenue_breakup_reports_manage',
            'finance_ledger_reports_manage', 'staff_listing_reports_manage',
            'staff_revenue_reports_manage', 'marketing_reports_manage', 'conversion_report_manage',
            'staff_wise_arrival_manage', 'non_converted_customers_manage', 'follow_up_manage',
            'followuppatient_manage'
        ];
        $reports_permissions = $this->buildPermissionGroup($reportsWhereIn, [], $isSuperAdmin, true);

        return [
            'permissions' => $permissions,
            'dashboard_permissions' => $dashboard_permissions,
            'reports_permissions' => $reports_permissions,
        ];
    }

    /**
     * Build a permission group with parent-child structure
     */
    private function buildPermissionGroup(array $filterArray, array $notInNamesArray, bool $isSuperAdmin, bool $useWhereIn): array
    {
        $baseQuery = Permission::where(['main_group' => 1, 'status' => 1]);
        
        if ($useWhereIn) {
            $baseQuery->whereIn('name', $filterArray);
        } else {
            $baseQuery->whereNotIn('name', $filterArray);
            if (!$isSuperAdmin) {
                $baseQuery->whereNotIn('name', $notInNamesArray);
            }
        }
        
        $groupPermissions = $baseQuery->orderBy('sort_order', 'asc')->get();
        $parentIds = $groupPermissions->pluck('id')->toArray();

        // Get all sub-permissions in one query, grouped by parent_id for efficient lookup
        $subPermissions = Permission::whereIn('parent_id', $parentIds)
            ->orderBy('sort_order', 'asc')
            ->get()
            ->groupBy('parent_id');

        $result = [];
        foreach ($groupPermissions as $groupPermission) {
            $parentId = $groupPermission->id;
            
            $result[$parentId] = [
                'id' => $parentId,
                'title' => $groupPermission->title,
                'name' => $groupPermission->name,
                'parent_id' => $groupPermission->parent_id,
                'children' => [],
                'key' => Str::replaceLast('manage', '', $groupPermission->name),
            ];

            // Get children for this parent (already grouped by parent_id)
            $children = $subPermissions->get($parentId, collect());
            foreach ($children as $subPermission) {
                $result[$parentId]['children'][$subPermission->name] = [
                    'id' => $subPermission->id,
                    'title' => $subPermission->title,
                    'name' => $subPermission->name,
                    'parent_id' => $subPermission->parent_id,
                ];
            }
        }

        return $result;
    }

    /**
     * Get allowed permissions for a role
     */
    public function getAllowedPermissions(?int $roleId = null): array
    {
        $query = Permission::join('role_has_permissions', 'role_has_permissions.permission_id', '=', 'permissions.id');
        
        if ($roleId) {
            $query->where('role_has_permissions.role_id', $roleId);
        }
        
        $permissions = $query->get()->pluck('name', 'id')->toArray();
        
        return $permissions ?: [];
    }

    /**
     * Create a new role
     */
    public function create(array $data): Role
    {
        $permissions = $data['permission'] ?? [];
        unset($data['permission'], $data['DataTables_Table_0_length']);
        
        $role = Role::create($data);
        $role->givePermissionTo($permissions);
        
        $this->clearCache();

        return $role;
    }

    /**
     * Update an existing role
     */
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

    /**
     * Duplicate a role
     */
    public function duplicate(array $data): Role
    {
        $permissions = $data['permission'] ?? [];
        unset($data['permission'], $data['DataTables_Table_0_length']);
        
        $role = Role::create($data);
        $role->givePermissionTo($permissions);
        
        $this->clearCache();

        return $role;
    }

    /**
     * Delete a role
     */
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

    /**
     * Bulk delete roles (only those without users)
     */
    public function bulkDelete(array $ids): array
    {
        $deleted = 0;
        $skipped = 0;
        
        $roles = Role::whereIn('id', $ids)->get();
        
        foreach ($roles as $role) {
            if (!$this->hasUsers($role->id)) {
                $role->delete();
                $deleted++;
            } else {
                $skipped++;
            }
        }
        
        if ($deleted > 0) {
            $this->clearCache();
        }

        return [
            'deleted' => $deleted,
            'skipped' => $skipped,
        ];
    }

    /**
     * Check if role has assigned users
     */
    public function hasUsers(int $roleId): bool
    {
        return DB::table('role_has_users')->where('role_id', $roleId)->exists();
    }

    /**
     * Find role by ID or fail
     */
    public function findOrFail(int $id): Role
    {
        return Role::findOrFail($id);
    }

    /**
     * Clear role-related cache
     */
    private function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY_PERMISSIONS_MAPPING . '.super');
        Cache::forget(self::CACHE_KEY_PERMISSIONS_MAPPING . '.normal');
    }

}
