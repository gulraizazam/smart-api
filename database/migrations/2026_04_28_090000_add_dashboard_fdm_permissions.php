<?php

declare(strict_types=1);

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

/**
 * FDM (Front Desk / Branch Manager) dashboard permissions.
 *
 * Adds a 4th tab "Dashboard - FDM" alongside Overview / Practitioners /
 * Marketing in the role-edit page (clusters under the "Dashboard"
 * category at the top). When an FDM-only role is assigned only the
 * `dashboard.fdm.*` perms — and zero perms in the other tab groups —
 * the SPA's tab-gating logic auto-hides every other tab, making the
 * FDM tab effectively the role's default dashboard view.
 *
 * Panels mix reused Overview signals (branch-scoped) with three new
 * FDM-specific widgets:
 *   - cash         : port of legacy /admin/cashflow/fdm
 *   - today_status_board : counts by appointment status for today
 *                          (replaces TodayActivitiesPanel for FDM)
 *   - no_show_trend      : week-over-week no-show + cancellation %
 *   - target_pacing      : MTD revenue vs branch monthly target
 *
 * Admin roles (Administrator / Super-Admin / Super Admin) are auto-
 * granted every panel perm to preserve "admin sees everything"
 * behaviour. Other roles default to no access — must be granted
 * explicitly per panel through the role-edit page.
 */
return new class extends Migration
{
    public function up(): void
    {
        $guardName = 'web';

        $children = [
            ['dashboard.fdm.cash.view', 'Cash (Live + Week)'],
            ['dashboard.fdm.today_status_board.view', "Today's Status Board"],
            ['dashboard.fdm.stats.view', 'Stats (sales / revenue / KPIs)'],
            ['dashboard.fdm.gender_revenue.view', 'Gender × Revenue'],
            ['dashboard.fdm.sales_momentum.view', 'Sales Momentum'],
            ['dashboard.fdm.avg_values.view', 'Average Values'],
            ['dashboard.fdm.new_returning.view', 'New vs Returning'],
            ['dashboard.fdm.arrival_rate.view', 'Arrival Rate'],
            ['dashboard.fdm.utilization.view', 'Practitioner Utilization'],
            ['dashboard.fdm.utilization_heatmap.view', 'Capacity Heatmap'],
            ['dashboard.fdm.at_risk.view', 'At-Risk Patients'],
            ['dashboard.fdm.client_retention.view', 'Client Retention Rate'],
            ['dashboard.fdm.branch_feedback.view', 'Branch Feedback'],
            ['dashboard.fdm.retention_cohorts.view', 'Retention Cohorts'],
            ['dashboard.fdm.no_show_trend.view', 'No-show / Cancellation Trend'],
            ['dashboard.fdm.target_pacing.view', 'Monthly Target Pacing'],
        ];

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
        // Force-set category on rerun in case the row pre-existed without it.
        $parent->forceFill(['category' => 'Dashboard'])->save();

        $allPanelPerms = [];
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
            $allPanelPerms[] = $name;
        }

        $adminRoleNames = ['Administrator', 'Super-Admin', 'Super Admin'];
        foreach (Role::whereIn('name', $adminRoleNames)->get() as $role) {
            $role->givePermissionTo($allPanelPerms);
        }
    }

    public function down(): void
    {
        $names = [
            'dashboard_fdm',
            'dashboard.fdm.cash.view',
            'dashboard.fdm.today_status_board.view',
            'dashboard.fdm.stats.view',
            'dashboard.fdm.gender_revenue.view',
            'dashboard.fdm.sales_momentum.view',
            'dashboard.fdm.avg_values.view',
            'dashboard.fdm.new_returning.view',
            'dashboard.fdm.arrival_rate.view',
            'dashboard.fdm.utilization.view',
            'dashboard.fdm.utilization_heatmap.view',
            'dashboard.fdm.at_risk.view',
            'dashboard.fdm.client_retention.view',
            'dashboard.fdm.branch_feedback.view',
            'dashboard.fdm.retention_cohorts.view',
            'dashboard.fdm.no_show_trend.view',
            'dashboard.fdm.target_pacing.view',
        ];

        Permission::whereIn('name', $names)->delete();
    }
};
