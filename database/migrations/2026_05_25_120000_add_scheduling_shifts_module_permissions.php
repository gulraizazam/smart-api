<?php

declare(strict_types=1);

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Scheduling Shifts module — fine-grained permission catalog
 * (`scheduling_shifts.<action>`).
 *
 * Adds 4 new permissions covering the scheduling-shifts calendar, the
 * repeating-shifts builder, and the time-off dialogs (time-offs are
 * managed inside the same page and share the shift perms — see SPA
 * `shift-edit-dialog.tsx`).
 *
 * Group `category='Clinic'` so the role editor places this group under
 * the sidebar's Clinic section, alongside Patients / Leads / Consultations
 * / Treatments — matching the sidebar's "Scheduling Shifts" entry which
 * sits in the Clinic group.
 *
 * IMPORTANT — partial migration: legacy `resourcerotas_*` perms stay alive
 * because they're still referenced by the admin-v1 Blade controller
 * (`Admin/ResourceRotasController`). The companion migration
 * 2026_05_25_130000 hides the legacy `resourcerotas_manage` umbrella from
 * the role editor so users only see the new dotted catalog. Legacy rows
 * are dropped when the admin-v1 retirement lands.
 *
 * Dangling perms — `resourcerotas_active`, `resourcerotas_inactive`,
 * `resourcerotas_calender` (typo) — have zero code references and will be
 * dropped in the same companion migration.
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
            'resourcerotas_manage' => ['scheduling_shifts.list.view'],
            'resourcerotas_create' => ['scheduling_shifts.create'],
            'resourcerotas_edit' => ['scheduling_shifts.edit'],
            'resourcerotas_destroy' => ['scheduling_shifts.delete'],
        ];
    }

    /**
     * @return list<array{name: string, title: string}>
     */
    private function newPerms(): array
    {
        return [
            ['name' => 'scheduling_shifts.list.view', 'title' => 'List · View'],
            ['name' => 'scheduling_shifts.create',    'title' => 'Create'],
            ['name' => 'scheduling_shifts.edit',      'title' => 'Edit'],
            ['name' => 'scheduling_shifts.delete',    'title' => 'Delete'],
        ];
    }

    public function up(): void
    {
        DB::transaction(function (): void {
            $group = Permission::updateOrCreate(
                ['name' => 'scheduling_shifts'],
                [
                    'title' => 'Scheduling Shifts',
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

            // Admin roles get every new perm.
            $newNames = array_map(static fn ($r) => $r['name'], $this->newPerms());
            foreach (Role::whereIn('name', self::ADMIN_ROLES)->get() as $role) {
                $role->givePermissionTo($newNames);
            }

            // Back-compat mirror — any role holding the legacy perm gets
            // the matching new perm so nothing breaks at runtime.
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

            Permission::where('name', 'scheduling_shifts')
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
