<?php

namespace App\Services\UserManagement;

use App\Models\Permission;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

class PermissionService
{
    private const CACHE_TTL = 3600; // 1 hour
    private const CACHE_KEY_PARENT_GROUPS = 'permissions.parent_groups';

    /**
     * Get paginated permissions for datatable
     */
    public function getDatatableData(array $params, bool $isSuperAdmin): array
    {
        $query = $this->buildBaseQuery($isSuperAdmin);
        
        $totalBeforeFilter = $query->count();
        
        if (!empty($params['search'])) {
            $this->applySearch($query, $params['search']);
        }
        
        if (isset($params['parent_id']) && $params['parent_id'] !== '') {
            $this->applyParentFilter($query, $params['parent_id']);
        }
        
        $totalFiltered = $query->count();
        
        $this->applySorting($query, $params['orderBy'] ?? 'name', $params['order'] ?? 'asc');
        
        $permissions = $query
            ->offset($params['offset'] ?? 0)
            ->limit($params['limit'] ?? 30)
            ->get();

        return [
            'data' => $permissions,
            'total' => $totalFiltered,
            'totalBeforeFilter' => $totalBeforeFilter,
        ];
    }

    /**
     * Build base query with eager loading
     */
    private function buildBaseQuery(bool $isSuperAdmin)
    {
        $query = Permission::query()
            ->select(['id', 'name', 'title', 'main_group', 'parent_id', 'status', 'guard_name', 'created_at', 'sort_order'])
            ->with(['parent:id,name']);

        if (!$isSuperAdmin) {
            $query->where('name', '!=', 'view_inactive_records');
        }

        return $query;
    }

    /**
     * Apply search filters to query
     */
    private function applySearch($query, string $search): void
    {
        $query->where(function ($q) use ($search) {
            $q->where('name', 'LIKE', "%{$search}%")
              ->orWhere('title', 'LIKE', "%{$search}%");
        });
    }

    /**
     * Apply parent filter to query
     */
    private function applyParentFilter($query, $parentId): void
    {
        if ($parentId === '0' || $parentId === 0) {
            // Show only parent groups (permissions with no parent)
            $query->whereNull('parent_id')->orWhere('parent_id', 0);
        } else {
            // Show children of selected parent
            $query->where('parent_id', (int) $parentId);
        }
    }

    /**
     * Apply sorting to query
     */
    private function applySorting($query, string $orderBy, string $order): void
    {
        // Handle parent.name sorting by joining
        if ($orderBy === 'parent.name') {
            $query->leftJoin('permissions as parent', 'permissions.parent_id', '=', 'parent.id')
                  ->orderBy('parent.name', $order)
                  ->select(['permissions.*']);
        } else {
            $query->orderBy($orderBy, $order);
        }
    }

    /**
     * Get parent groups for dropdown (cached)
     */
    public function getParentGroups(): array
    {
        return Cache::remember(self::CACHE_KEY_PARENT_GROUPS, self::CACHE_TTL, function () {
            $permissions = [
                '' => 'Select a Parent Group',
                0 => 'This is Parent Group',
            ];

            Permission::parentGroups()
                ->orderBy('sort_order', 'asc')
                ->select(['id', 'name', 'title', 'sort_order'])
                ->get()
                ->each(function ($permission) use (&$permissions) {
                    $permissions[$permission->id] = $permission->title . ' (' . $permission->name . ')';
                });

            return $permissions;
        });
    }

    /**
     * Create a new permission
     */
    public function create(array $data): Permission
    {
        $data['main_group'] = empty($data['parent_id']) ? 1 : 0;
        $data['guard_name'] = $data['guard_name'] ?? 'web';

        $permission = Permission::create($data);
        
        $this->clearCache();

        return $permission;
    }

    /**
     * Update an existing permission
     */
    public function update(int $id, array $data): Permission
    {
        $permission = $this->findOrFail($id);
        
        $data['main_group'] = empty($data['parent_id']) ? 1 : 0;

        $permission->update($data);
        
        $this->clearCache();

        return $permission->fresh();
    }

    /**
     * Delete a permission and its children if it's a parent
     */
    public function delete(int $id): bool
    {
        $permission = $this->findOrFail($id);
        
        // Delete children first if this is a parent permission
        if ($permission->main_group) {
            Permission::where('parent_id', $id)->delete();
        }
        
        $deleted = $permission->delete();
        
        $this->clearCache();

        return $deleted;
    }

    /**
     * Bulk delete permissions and their children
     */
    public function bulkDelete(array $ids): int
    {
        // Find parent permissions in the list and delete their children first
        $parentIds = Permission::whereIn('id', $ids)
            ->where('main_group', 1)
            ->pluck('id')
            ->toArray();
        
        if (!empty($parentIds)) {
            Permission::whereIn('parent_id', $parentIds)->delete();
        }
        
        $deleted = Permission::whereIn('id', $ids)->delete();
        
        $this->clearCache();

        return $deleted;
    }

    /**
     * Find permission by ID or fail
     */
    public function findOrFail(int $id): Permission
    {
        return Permission::findOrFail($id);
    }

    /**
     * Get user permissions for the module
     */
    public function getUserPermissions(): array
    {
        return [
            'create' => Gate::allows('permissions_create'),
            'edit' => Gate::allows('permissions_edit'),
            'delete' => Gate::allows('permissions_destroy'),
            'manage' => Gate::allows('permissions_manage'),
        ];
    }

    /**
     * Clear permission-related cache
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY_PARENT_GROUPS);
    }
}
