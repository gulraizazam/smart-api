<?php

declare(strict_types=1);

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

/**
 * Seeds Management Dashboard permissions + role.
 *
 * Permission hierarchy (one parent group + four children):
 *   management_dashboard                (group header — main_group=1)
 *     ├ management_dashboard.view              view the dashboard at all
 *     ├ management_dashboard.manage_targets    edit branch/system targets in-dashboard
 *     ├ management_dashboard.manage_alerts     edit alert threshold overrides
 *     └ management_dashboard.acknowledge_alerts ack + snooze live alerts
 *
 * Branch visibility is NOT a permission — it's data-driven via the
 * `user_branches` pivot (empty = company view, populated = regional subset).
 * That separation lets ops assign scope without touching the perm matrix.
 *
 * Grants:
 *   - Administrator / Super-Admin / Super Admin: all four permissions (preserving
 *     existing "admin sees everything" behavior).
 *   - New "Management" role: view + acknowledge_alerts only (target / alert-
 *     threshold editing is typically an admin responsibility; elevate per-user
 *     later if needed).
 */
return new class extends Migration
{
    public function up(): void
    {
        $guardName = 'web';

        $parent = Permission::firstOrCreate(
            ['name' => 'management_dashboard'],
            [
                'title' => 'Management Dashboard',
                'main_group' => 1,
                'parent_id' => 0,
                'status' => 1,
                'guard_name' => $guardName,
            ]
        );

        $children = [
            ['management_dashboard.view', 'View Dashboard'],
            ['management_dashboard.manage_targets', 'Manage Targets'],
            ['management_dashboard.manage_alerts', 'Manage Alert Thresholds'],
            ['management_dashboard.acknowledge_alerts', 'Acknowledge / Snooze Alerts'],
        ];

        foreach ($children as [$name, $title]) {
            Permission::firstOrCreate(
                ['name' => $name],
                [
                    'title' => $title,
                    'main_group' => 0,
                    'parent_id' => $parent->id,
                    'status' => 1,
                    'guard_name' => $guardName,
                ]
            );
        }

        $managementRole = Role::firstOrCreate(
            ['name' => 'Management', 'guard_name' => $guardName],
            ['commission' => 0.00],
        );
        $managementRole->givePermissionTo([
            'management_dashboard.view',
            'management_dashboard.acknowledge_alerts',
        ]);

        $adminRoleNames = ['Administrator', 'Super-Admin', 'Super Admin'];
        foreach (Role::whereIn('name', $adminRoleNames)->get() as $role) {
            $role->givePermissionTo([
                'management_dashboard.view',
                'management_dashboard.manage_targets',
                'management_dashboard.manage_alerts',
                'management_dashboard.acknowledge_alerts',
            ]);
        }
    }

    public function down(): void
    {
        Permission::whereIn('name', [
            'management_dashboard',
            'management_dashboard.view',
            'management_dashboard.manage_targets',
            'management_dashboard.manage_alerts',
            'management_dashboard.acknowledge_alerts',
        ])->delete();

        Role::where('name', 'Management')->delete();
    }
};
