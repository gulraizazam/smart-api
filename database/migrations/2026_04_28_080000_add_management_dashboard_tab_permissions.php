<?php

declare(strict_types=1);

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

/**
 * Granular Management Dashboard permissions — one parent per tab, one
 * child per visible panel. Tabs auto-hide on the SPA when the user has
 * zero permissions for any panel in that tab.
 *
 * Hierarchy on the role-edit page (clusters under the "Dashboard"
 * category at the top):
 *
 *   Dashboard - Overview       (parent)
 *     ├ stats.view                       (Stats tiles)
 *     ├ today_activities.view
 *     ├ cash_flow.view                   (Cash Flow Statement)
 *     ├ gender_revenue.view
 *     ├ sales_momentum.view
 *     ├ avg_values.view
 *     ├ new_returning.view
 *     ├ client_retention.view
 *     ├ at_risk.view
 *     ├ retention_cohorts.view
 *     ├ branch_feedback.view
 *     ├ utilization.view
 *     ├ utilization_heatmap.view
 *     └ arrival_rate.view
 *   Dashboard - Practitioners  (parent)
 *     └ list.view
 *   Dashboard - Marketing      (parent)
 *     ├ service_interest.view
 *     ├ lead_conversion.view
 *     ├ service_category_trend.view
 *     └ sales_concentration.view
 *
 * Existing `management_dashboard` parent + its action children
 * (manage_targets / manage_alerts / acknowledge_alerts /
 * view) are kept as-is. Their `category` is also set to "Dashboard"
 * here so all dashboard-related groups render together.
 *
 * Admin roles (Administrator / Super-Admin / Super Admin) are auto-
 * granted every panel perm to preserve "admin sees everything"
 * behaviour. Other roles default to no access — must be granted
 * explicitly per panel.
 */
return new class extends Migration
{
    public function up(): void
    {
        $guardName = 'web';

        // Group existing dashboard parent under the same category so it
        // lands in the same role-edit section as the new tab parents.
        Permission::where('name', 'management_dashboard')->update(['category' => 'Dashboard']);

        $tabs = [
            [
                'name' => 'dashboard_overview',
                'title' => 'Dashboard - Overview',
                'children' => [
                    ['dashboard.overview.stats.view', 'Stats (sales / revenue / KPIs)'],
                    ['dashboard.overview.today_activities.view', 'Today Activities'],
                    ['dashboard.overview.cash_flow.view', 'Cash Flow Statement'],
                    ['dashboard.overview.gender_revenue.view', 'Gender × Revenue'],
                    ['dashboard.overview.sales_momentum.view', 'Sales Momentum'],
                    ['dashboard.overview.avg_values.view', 'Average Values'],
                    ['dashboard.overview.new_returning.view', 'New vs Returning'],
                    ['dashboard.overview.client_retention.view', 'Client Retention Rate'],
                    ['dashboard.overview.at_risk.view', 'At-Risk Patients'],
                    ['dashboard.overview.retention_cohorts.view', 'Retention Cohorts'],
                    ['dashboard.overview.branch_feedback.view', 'Branch Feedback'],
                    ['dashboard.overview.utilization.view', 'Practitioner Utilization'],
                    ['dashboard.overview.utilization_heatmap.view', 'Capacity Heatmap'],
                    ['dashboard.overview.arrival_rate.view', 'Arrival Rate'],
                ],
            ],
            [
                'name' => 'dashboard_practitioners',
                'title' => 'Dashboard - Practitioners',
                'children' => [
                    ['dashboard.practitioners.list.view', 'Practitioner List'],
                ],
            ],
            [
                'name' => 'dashboard_marketing',
                'title' => 'Dashboard - Marketing',
                'children' => [
                    ['dashboard.marketing.service_interest.view', 'Service Interest'],
                    ['dashboard.marketing.lead_conversion.view', 'Lead Conversion'],
                    ['dashboard.marketing.service_category_trend.view', 'Service Category Trend'],
                    ['dashboard.marketing.sales_concentration.view', 'Sales Concentration'],
                ],
            ],
        ];

        $allPanelPerms = [];
        foreach ($tabs as $tab) {
            $parent = Permission::firstOrCreate(
                ['name' => $tab['name']],
                [
                    'title' => $tab['title'],
                    'main_group' => 1,
                    'parent_id' => 0,
                    'status' => 1,
                    'category' => 'Dashboard',
                    'guard_name' => $guardName,
                ]
            );
            // firstOrCreate doesn't update an existing row's category if
            // the name pre-exists; force-set it so reruns settle correctly.
            $parent->forceFill(['category' => 'Dashboard'])->save();

            foreach ($tab['children'] as [$name, $title]) {
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
        }

        // Grant every new panel perm to admin roles so existing behaviour
        // (admins see everything) is preserved.
        $adminRoleNames = ['Administrator', 'Super-Admin', 'Super Admin'];
        foreach (Role::whereIn('name', $adminRoleNames)->get() as $role) {
            $role->givePermissionTo($allPanelPerms);
        }
    }

    public function down(): void
    {
        $names = [
            'dashboard_overview',
            'dashboard_practitioners',
            'dashboard_marketing',
            'dashboard.overview.stats.view',
            'dashboard.overview.today_activities.view',
            'dashboard.overview.cash_flow.view',
            'dashboard.overview.gender_revenue.view',
            'dashboard.overview.sales_momentum.view',
            'dashboard.overview.avg_values.view',
            'dashboard.overview.new_returning.view',
            'dashboard.overview.client_retention.view',
            'dashboard.overview.at_risk.view',
            'dashboard.overview.retention_cohorts.view',
            'dashboard.overview.branch_feedback.view',
            'dashboard.overview.utilization.view',
            'dashboard.overview.utilization_heatmap.view',
            'dashboard.overview.arrival_rate.view',
            'dashboard.practitioners.list.view',
            'dashboard.marketing.service_interest.view',
            'dashboard.marketing.lead_conversion.view',
            'dashboard.marketing.service_category_trend.view',
            'dashboard.marketing.sales_concentration.view',
        ];

        Permission::whereIn('name', $names)->delete();
    }
};
