<?php

declare(strict_types=1);

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Adds a single new permission — `leads.call` — for the click-to-call button
 * on the lead detail drawer, and grants it to admin roles + any role that
 * already holds `leads.list.view_contact` (the previous, over-broad gate
 * for the same action on `LeadPolicy::call()`).
 *
 * Mirrors 2026_05_21_120000_add_leads_module_permissions structure exactly
 * (guard, admin role list, cache eviction). Kept minimal because the
 * `leads` group perm already exists.
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
            $group = Permission::where('name', 'leads')
                ->where('main_group', 1)
                ->first();

            // If the group perm hasn't been seeded yet (fresh DB running this
            // migration before its 2026_05_21_120000 sibling), just create it.
            if (! $group) {
                $group = Permission::updateOrCreate(
                    ['name' => 'leads'],
                    [
                        'title' => 'Leads',
                        'main_group' => 1,
                        'parent_id' => 0,
                        'status' => 1,
                        'category' => 'Leads',
                        'guard_name' => self::GUARD,
                    ],
                );
            }

            Permission::updateOrCreate(
                ['name' => 'leads.call'],
                [
                    'title' => 'Call Lead (click-to-call)',
                    'main_group' => 0,
                    'parent_id' => $group->id,
                    'status' => 1,
                    'category' => null,
                    'guard_name' => self::GUARD,
                    'sort_order' => 100, // after everything else in the group
                ],
            );

            // Admin roles always get it.
            foreach (Role::whereIn('name', self::ADMIN_ROLES)->get() as $role) {
                $role->givePermissionTo('leads.call');
            }

            // Back-compat mirror: any role currently holding leads.list.view_contact
            // (which used to gate LeadPolicy::call()) also gets leads.call. Skips
            // admin roles because they already got it above.
            $adminRoleIds = Role::whereIn('name', self::ADMIN_ROLES)->pluck('id')->all();
            $legacy = Permission::where('name', 'leads.list.view_contact')
                ->where('guard_name', self::GUARD)
                ->first();
            if ($legacy) {
                $roleIds = DB::table('role_has_permissions')
                    ->where('permission_id', $legacy->id)
                    ->whereNotIn('role_id', $adminRoleIds)
                    ->pluck('role_id')
                    ->all();
                foreach (Role::whereIn('id', $roleIds)->get() as $role) {
                    $role->givePermissionTo('leads.call');
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
            Permission::where('name', 'leads.call')->delete();

            app(PermissionRegistrar::class)->forgetCachedPermissions();
            foreach (self::ROLE_SERVICE_CACHE_KEYS as $key) {
                Cache::forget($key);
            }
        });
    }
};
