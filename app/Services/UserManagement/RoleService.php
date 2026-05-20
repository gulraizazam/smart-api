<?php

declare(strict_types=1);
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
    private const CACHE_KEY_PERMISSIONS_MAPPING = 'roles.permissions_mapping.v2';

    /**
     * Fixed render order for permission categories on the role edit/create UI.
     * Categories mirror the SPA sidebar groups (see sidebar-data.ts) so the
     * role editor reads in the same order the sidebar surfaces modules.
     * A category whose value is `null` in the DB is rendered in `OTHER_CATEGORY`
     * at the end so nothing silently disappears if a new permission is added
     * without a category backfill.
     *
     * Companion migration: 2026_05_23_120000_recategorise_permissions_to_sidebar
     * rewrites every visible group header's category to one of these values.
     */
    private const CATEGORY_ORDER = [
        'Dashboard', 'Clinic', 'Catalog', 'Finance', 'Cash Flow',
        'Inventory', 'Reports', 'People', 'Settings',
    ];

    private const OTHER_CATEGORY = 'Other';
    private const DASHBOARD_CATEGORY = 'Dashboard';
    private const REPORTS_CATEGORY = 'Reports';

    public function getDatatableData(array $params): array
    {
        $query = Role::query()->withCount('users');

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
        $notInNamesForNonSuper = [
            'view_inactive_users', 'view_inactive_appointment_statuses', 'view_inactive_centres',
            'view_inactive_cities', 'view_inactive_discounts', 'view_inactive_doctors',
            'view_inactive_lead_sources', 'leads.list.view_inactive', 'view_inactive_lead_statuses',
            'view_inactive_machine_types', 'packages.list.view_inactive', 'patients.list.view_inactive',
            'view_inactive_payment_modes', 'plans.list.view_inactive', 'view_inactive_products',
            'view_inactive_regions', 'view_inactive_custom_forms', 'view_inactive_towns',
            'view_inactive_resources', 'view_inactive_rota', 'view_inactive_rotas',
            'services.list.view_inactive', 'view_inactive_sms_templates',
        ];

        $isSuperAdmin = Auth::user()->hasRole('Super-Admin');

        $allGroups = $this->buildPermissionGroup(
            excludeParentNames: $isSuperAdmin ? [] : $notInNamesForNonSuper,
        );

        $groupsByCategory = [];
        foreach ($allGroups as $groupId => $group) {
            $category = $group['category'] ?? null;
            $bucket = $category !== null && $category !== '' ? $category : self::OTHER_CATEGORY;
            $groupsByCategory[$bucket][$groupId] = $group;
        }

        $categories = [];
        foreach (self::CATEGORY_ORDER as $categoryName) {
            if (!empty($groupsByCategory[$categoryName])) {
                $categories[$categoryName] = $groupsByCategory[$categoryName];
                unset($groupsByCategory[$categoryName]);
            }
        }
        foreach ($groupsByCategory as $categoryName => $groups) {
            $categories[$categoryName] = $groups;
        }

        $dashboardPermissions = $categories[self::DASHBOARD_CATEGORY] ?? [];
        $reportsPermissions = $categories[self::REPORTS_CATEGORY] ?? [];

        $generalPermissions = [];
        foreach ($categories as $categoryName => $groups) {
            if ($categoryName === self::DASHBOARD_CATEGORY || $categoryName === self::REPORTS_CATEGORY) {
                continue;
            }
            foreach ($groups as $groupId => $group) {
                $generalPermissions[$groupId] = $group;
            }
        }

        return [
            'categories' => $categories,
            'permissions' => $generalPermissions,
            'dashboard_permissions' => $dashboardPermissions,
            'reports_permissions' => $reportsPermissions,
        ];
    }

    private function buildPermissionGroup(array $excludeParentNames = []): array
    {
        $query = Permission::where(['main_group' => 1, 'status' => 1]);

        if (!empty($excludeParentNames)) {
            $query->whereNotIn('name', $excludeParentNames);
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
                'category' => $group->category ?? null,
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

        // roles.commission is NOT NULL with no DB default; coerce null/missing to 0.
        $data['commission'] = $data['commission'] ?? 0;

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

        // roles.commission is NOT NULL with no DB default; coerce null to 0.
        if (array_key_exists('commission', $data) && $data['commission'] === null) {
            $data['commission'] = 0;
        }

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
        // Transitional: evict v1 keys that may linger from pre-deploy sessions.
        Cache::forget('roles.permissions_mapping.super');
        Cache::forget('roles.permissions_mapping.normal');

        // Spatie caches the role->permission relation for 24h via the
        // PermissionRegistrar. Without this, an admin saving a role takes
        // up to a day to actually change `$user->can(...)` for everyone
        // assigned that role — they have to re-login to force a fresh
        // load, which is the pain users have been hitting in testing.
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
