<?php

declare(strict_types=1);

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Adds the FDM dashboard "Today's Activities" panel permission.
 *
 * The FDM tab now surfaces the live activity feed (payments / refunds /
 * cancellations) in its top block, scoped to the FDM's branch. Per the
 * one-gate-per-panel pattern (see 2026_04_28_090000_add_dashboard_fdm_permissions),
 * the panel needs its own `dashboard.fdm.today_activities.view` gate.
 *
 * Additive + idempotent: creates the permission under the existing
 * `dashboard_fdm` parent and grants it to the admin roles + the FDM role so
 * the panel is visible immediately. Affected users must re-login or refresh
 * /api/user for the Spatie cache to pick it up.
 */
return new class extends Migration
{
    private const PERM = 'dashboard.fdm.today_activities.view';

    public function up(): void
    {
        $guardName = 'web';

        $parent = Permission::firstOrCreate(
            ['name' => 'dashboard_fdm'],
            [
                'title' => 'Dashboard - FDM',
                'main_group' => 1,
                'parent_id' => 0,
                'status' => 1,
                'category' => 'Dashboard',
                'guard_name' => $guardName,
            ]
        );

        Permission::firstOrCreate(
            ['name' => self::PERM],
            [
                'title' => "Today's Activities",
                'main_group' => 0,
                'parent_id' => $parent->id,
                'status' => 1,
                'guard_name' => $guardName,
            ]
        );

        $roleNames = ['Administrator', 'Super-Admin', 'Super Admin', 'FDM'];
        foreach (Role::whereIn('name', $roleNames)->get() as $role) {
            $role->givePermissionTo(self::PERM);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', self::PERM)->delete();
    }
};
