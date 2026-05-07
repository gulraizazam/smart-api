<?php

declare(strict_types=1);
namespace App\Services\UserManagement;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

class PermissionService
{
    private const CACHE_TTL = 3600;
    private const CACHE_KEY_PARENT_GROUPS = 'permissions.parent_groups';
    private const CACHE_KEY_TREE = 'permissions.tree';

    public function getDatatableData(array $params, bool $isSuperAdmin): array
    {
        $query = $this->buildBaseQuery($isSuperAdmin);

        $totalBeforeFilter = $query->count();

        if (!empty($params['search'])) {
            $query->where(fn (Builder $q) => $q
                ->where('name', 'LIKE', "%{$params['search']}%")
                ->orWhere('title', 'LIKE', "%{$params['search']}%")
            );
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

    private function buildBaseQuery(bool $isSuperAdmin): Builder
    {
        return Permission::query()
            ->select(['id', 'name', 'title', 'main_group', 'parent_id', 'status', 'guard_name', 'created_at', 'sort_order'])
            ->with('parent:id,name')
            ->when(!$isSuperAdmin, fn (Builder $q) => $q->where('name', '!=', 'view_inactive_records'));
    }

    private function applyParentFilter(Builder $query, mixed $parentId): void
    {
        if ($parentId === '0' || $parentId === 0) {
            $query->where(fn (Builder $q) => $q->whereNull('parent_id')->orWhere('parent_id', 0));
        } else {
            $query->where('parent_id', (int) $parentId);
        }
    }

    private function applySorting(Builder $query, string $orderBy, string $order): void
    {
        if ($orderBy === 'parent.name') {
            $query->leftJoin('permissions as parent', 'permissions.parent_id', '=', 'parent.id')
                ->orderBy('parent.name', $order)
                ->select('permissions.*');
        } else {
            $query->orderBy($orderBy, $order);
        }
    }

    public function getParentGroups(): array
    {
        return Cache::remember(self::CACHE_KEY_PARENT_GROUPS, self::CACHE_TTL, function (): array {
            $permissions = [
                '' => 'Select a Parent Group',
                0 => 'This is Parent Group',
            ];

            Permission::parentGroups()
                ->orderBy('sort_order')
                ->select(['id', 'name', 'title', 'sort_order'])
                ->each(function (Permission $p) use (&$permissions) {
                    $permissions[$p->id] = "{$p->title} ({$p->name})";
                });

            return $permissions;
        });
    }

    public function create(array $data): Permission
    {
        $data['main_group'] = empty($data['parent_id']) ? 1 : 0;
        $data['guard_name'] ??= 'web';

        $permission = Permission::create($data);

        $this->clearCache();

        return $permission;
    }

    public function update(int $id, array $data): Permission
    {
        $permission = $this->findOrFail($id);

        $data['main_group'] = empty($data['parent_id']) ? 1 : 0;

        $permission->update($data);

        $this->clearCache();

        return $permission->fresh();
    }

    public function delete(int $id): bool
    {
        $permission = $this->findOrFail($id);

        if ($permission->isParentGroup()) {
            Permission::where('parent_id', $id)->delete();
        }

        $deleted = $permission->delete();

        $this->clearCache();

        return $deleted;
    }

    public function findOrFail(int $id): Permission
    {
        return Permission::findOrFail($id);
    }

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
     * Two-level catalogue: parent groups with their children nested under
     * a `children` key. Drives the SPA's tree view at /permissions.
     *
     * Cached for an hour and busted in clearCache() — same pattern as
     * getParentGroups so the catalogue stays consistent across CRUD.
     *
     * @return array<int, array{id:int,name:string,title:?string,status:bool,guard_name:string,children:array<int, array{id:int,name:string,title:?string,status:bool,guard_name:string,parent_id:?int}>}>
     */
    public function getTree(bool $isSuperAdmin): array
    {
        $cacheKey = self::CACHE_KEY_TREE.'.'.($isSuperAdmin ? 'sa' : 'std');

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($isSuperAdmin): array {
            $base = Permission::query()
                ->select(['id', 'name', 'title', 'parent_id', 'status', 'guard_name', 'sort_order'])
                ->when(!$isSuperAdmin, fn (Builder $q) => $q->where('name', '!=', 'view_inactive_records'))
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();

            $childrenByParent = $base
                ->filter(fn (Permission $p) => !empty($p->parent_id))
                ->groupBy('parent_id');

            return $base
                ->filter(fn (Permission $p) => empty($p->parent_id))
                ->map(fn (Permission $parent) => [
                    'id' => (int) $parent->id,
                    'name' => (string) $parent->name,
                    'title' => $parent->title,
                    'status' => (bool) $parent->status,
                    'guard_name' => (string) $parent->guard_name,
                    'children' => ($childrenByParent[$parent->id] ?? collect())
                        ->map(fn (Permission $c) => [
                            'id' => (int) $c->id,
                            'name' => (string) $c->name,
                            'title' => $c->title,
                            'status' => (bool) $c->status,
                            'guard_name' => (string) $c->guard_name,
                            'parent_id' => $c->parent_id !== null ? (int) $c->parent_id : null,
                        ])
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all();
        });
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY_PARENT_GROUPS);
        Cache::forget(self::CACHE_KEY_TREE.'.sa');
        Cache::forget(self::CACHE_KEY_TREE.'.std');
    }
}
