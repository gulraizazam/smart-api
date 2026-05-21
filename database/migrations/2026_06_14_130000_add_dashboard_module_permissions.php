<?php

declare(strict_types=1);

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Dashboard module — only the `doctor_dashboard` umbrella gets a dotted
 * normalisation. The 16 legacy `dashboard_<widget>` rows under
 * `dashboard_manage` are intentionally NOT migrated — those widgets
 * live on the legacy Bootstrap admin dashboard, which is being retired
 * (see project memory `project_blade_retirement_imminent`). The SPA
 * has zero references to any `dashboard_*` legacy perm and lands on
 * its own management-dashboard surface, which already runs on the new
 * dotted catalogs (`dashboard.overview.*` / `.practitioners.*` /
 * `.marketing.*` / `.fdm.*`, plus the `management_dashboard.view`
 * route-group gate).
 *
 * Migrating the 16 widget rows would just create rows that need to die
 * the moment the Blade dashboard goes away. Better to let them retire
 * in place when the legacy admin does.
 *
 * Catalog (group head `doctor_dashboard_perm` + 1 child):
 *   - doctor_dashboard.view
 *
 * Naming note: the LEGACY `doctor_dashboard` row is both a main_group=1
 * row (group head) and the perm name itself — the same overloaded
 * pattern as `contact` (handled in the Settings remainder migration).
 * The new dotted catalog uses a different group head name
 * (`doctor_dashboard_perm`) to avoid colliding with the legacy row,
 * and the child is `doctor_dashboard.view`. Mirror grants attach the
 * existing `doctor_dashboard` grants onto the new `.view` perm so the
 * `HomeController` legacy-admin redirect keeps working through the
 * cutover.
 *
 * IMPORTANT — partial migration: legacy rows stay at status=1 because
 * `HomeController` still calls `$user->can('doctor_dashboard')` to
 * route doctors to the legacy doctor dashboard. Once the SPA owns
 * that routing, the legacy `doctor_dashboard` row can be hidden
 * alongside the rest of the dashboard widget rows.
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

    public function up(): void
    {
        DB::transaction(function (): void {
            // New group head — distinct name from the legacy
            // `doctor_dashboard` row (which is itself a main_group=1
            // row that doubles as a perm).
            $group = Permission::updateOrCreate(
                ['name' => 'doctor_dashboard_perm'],
                [
                    'title' => 'Doctor Dashboard',
                    'main_group' => 1,
                    'parent_id' => 0,
                    'status' => 1,
                    'category' => 'Dashboard',
                    'guard_name' => self::GUARD,
                ],
            );

            $perm = Permission::updateOrCreate(
                ['name' => 'doctor_dashboard.view'],
                [
                    'title' => 'View doctor dashboard',
                    'main_group' => 0,
                    'parent_id' => $group->id,
                    'status' => 1,
                    'category' => null,
                    'guard_name' => self::GUARD,
                    'sort_order' => 1,
                ],
            );

            // Admin roles: pick up the new perm.
            foreach (Role::whereIn('name', self::ADMIN_ROLES)->get() as $role) {
                $role->givePermissionTo('doctor_dashboard.view');
            }

            // Mirror: any role that currently holds the legacy
            // `doctor_dashboard` perm picks up the new `.view` so the
            // HomeController redirect keeps working.
            $legacy = DB::table('permissions')->where('name', 'doctor_dashboard')->first();
            if ($legacy !== null) {
                $roleIds = DB::table('role_has_permissions')
                    ->where('permission_id', $legacy->id)
                    ->pluck('role_id');
                foreach ($roleIds as $roleId) {
                    DB::table('role_has_permissions')->updateOrInsert(
                        ['role_id' => $roleId, 'permission_id' => $perm->id],
                        [],
                    );
                }
                $directGrants = DB::table('model_has_permissions')
                    ->where('permission_id', $legacy->id)
                    ->get(['model_id', 'model_type']);
                foreach ($directGrants as $grant) {
                    DB::table('model_has_permissions')->updateOrInsert(
                        [
                            'permission_id' => $perm->id,
                            'model_id' => $grant->model_id,
                            'model_type' => $grant->model_type,
                        ],
                        [],
                    );
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
            Permission::where('name', 'doctor_dashboard.view')->delete();
            Permission::where('name', 'doctor_dashboard_perm')
                ->where('main_group', 1)
                ->where('category', 'Dashboard')
                ->delete();

            app(PermissionRegistrar::class)->forgetCachedPermissions();
            foreach (self::ROLE_SERVICE_CACHE_KEYS as $key) {
                Cache::forget($key);
            }
        });
    }
};
