<?php

declare(strict_types=1);

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Business Closures module — fine-grained permission catalog
 * (`business_closures.<action>`).
 *
 * Adds 4 new permissions for the /business-closures SPA page. Group
 * `category='Clinic'` so it lands in the sidebar's Clinic section (the
 * sidebar nests "Business Closed Periods" under Clinic alongside
 * Scheduling Shifts).
 *
 * Companion to 2026_05_26_130000 which hides the legacy
 * `business_closures_manage` umbrella from the role editor. Legacy
 * `business_closures_*` rows stay alive at runtime — gate calls in
 * controllers / blade views resolve them through Spatie regardless of
 * the row's `status` flag — but the role editor only shows the new
 * dotted group.
 */
return new class extends Migration
{
    private const GUARD = 'web';

    private const ADMIN_ROLES = ['Administrator', 'Super-Admin', 'Super Admin'];

    private const ROLE_SERVICE_CACHE_KEYS = [
        'roles.permissions_mapping.v2.super',
        'roles.permissions_mapping.v2.normal',
        'roles.permissions_mapping.super',
        'roles.permissions_mapping.normal',
    ];

    /**
     * @return array<string, list<string>>
     */
    private function legacyToNewMap(): array
    {
        return [
            'business_closures_manage' => ['business_closures.list.view'],
            'business_closures_create' => ['business_closures.create'],
            'business_closures_edit' => ['business_closures.edit'],
            'business_closures_delete' => ['business_closures.delete'],
        ];
    }

    /**
     * @return list<array{name: string, title: string}>
     */
    private function newPerms(): array
    {
        return [
            ['name' => 'business_closures.list.view', 'title' => 'List · View'],
            ['name' => 'business_closures.create',    'title' => 'Create'],
            ['name' => 'business_closures.edit',      'title' => 'Edit'],
            ['name' => 'business_closures.delete',    'title' => 'Delete'],
        ];
    }

    public function up(): void
    {
        DB::transaction(function (): void {
            $group = Permission::updateOrCreate(
                ['name' => 'business_closures'],
                [
                    'title' => 'Business Closures',
                    'main_group' => 1,
                    'parent_id' => 0,
                    'status' => 1,
                    'category' => 'Clinic',
                    'guard_name' => self::GUARD,
                ],
            );

            foreach ($this->newPerms() as $i => $row) {
                Permission::updateOrCreate(
                    ['name' => $row['name']],
                    [
                        'title' => $row['title'],
                        'main_group' => 0,
                        'parent_id' => $group->id,
                        'status' => 1,
                        'category' => null,
                        'guard_name' => self::GUARD,
                        'sort_order' => $i + 1,
                    ],
                );
            }

            $newNames = array_map(static fn ($r) => $r['name'], $this->newPerms());
            foreach (Role::whereIn('name', self::ADMIN_ROLES)->get() as $role) {
                $role->givePermissionTo($newNames);
            }

            $adminRoleIds = Role::whereIn('name', self::ADMIN_ROLES)
                ->pluck('id')
                ->all();
            foreach ($this->legacyToNewMap() as $legacy => $news) {
                $legacyPerm = Permission::where('name', $legacy)
                    ->where('guard_name', self::GUARD)
                    ->first();
                if (! $legacyPerm) {
                    continue;
                }
                $roleIds = DB::table('role_has_permissions')
                    ->where('permission_id', $legacyPerm->id)
                    ->whereNotIn('role_id', $adminRoleIds)
                    ->pluck('role_id')
                    ->all();
                foreach (Role::whereIn('id', $roleIds)->get() as $role) {
                    $role->givePermissionTo($news);
                }
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();
            foreach (self::ROLE_SERVICE_CACHE_KEYS as $key) {
                Cache::forget($key);
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $names = array_map(static fn ($r) => $r['name'], $this->newPerms());
            Permission::whereIn('name', $names)->delete();

            Permission::where('name', 'business_closures')
                ->where('main_group', 1)
                ->where('category', 'Clinic')
                ->delete();

            app(PermissionRegistrar::class)->forgetCachedPermissions();
            foreach (self::ROLE_SERVICE_CACHE_KEYS as $key) {
                Cache::forget($key);
            }
        });
    }
};
