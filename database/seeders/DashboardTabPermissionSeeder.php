<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Management Dashboard tab permissions — idempotent, self-healing.
 *
 * Why this exists: the per-panel `dashboard.<tab>.<panel>.view` gates were
 * introduced via one-shot migrations (2026_04_28_080000 + 2026_04_28_090000)
 * that grant the panels to admin roles ONLY. Migrations don't re-run, so an
 * environment where they never ran (or ran before the FDM role needed access)
 * cannot self-heal — an FDM user there gets a blank dashboard ("no dashboard
 * sections"). This seeder guarantees the full catalog exists + is categorised
 * (so any section is assignable to any role in the role editor) and grants the
 * FDM role its purpose-built FDM tab so the screen is no longer blank.
 *
 * Safe to run repeatedly and standalone:
 *   php artisan db:seed --class=DashboardTabPermissionSeeder
 *
 * Catalog mirrors the SPA's DASHBOARD_PERMS
 * (cutera-admin-spa/src/lib/management-dashboard/permissions.ts) and the two
 * source migrations verbatim. Keep all three in sync.
 *
 * Also guarantees + grants the cross-module `cashflow_fdm_view` gate (the FDM
 * Cash panel hits /api/cashflow/fdm/data, which CheckPermission gates on it)
 * to FDM + admins — it ships only in the unregistered CashflowPermissionsSeeder
 * and is granted to no role, so the FDM cash widget is dead without this.
 *
 * NOTE: Spatie caches permissions for 24h and RoleService caches the
 * role-editor permission tree; both are evicted at the end so changes are
 * effective immediately. Affected FDM users must still re-login or hit
 * /api/user (the SPA caches user.permissions in local/sessionStorage).
 */
class DashboardTabPermissionSeeder extends Seeder
{
    private const GUARD = 'web';

    /** Roles that always get every dashboard panel ("admin sees everything"). */
    private const ADMIN_ROLES = ['Administrator', 'Super-Admin', 'Super Admin'];

    /**
     * Cashflow gate the FDM dashboard's Cash panel depends on. It is created
     * only by CashflowPermissionsSeeder (unregistered, grants no role), so we
     * guarantee + grant it here too — the FDM tab is dead without it.
     */
    private const CASHFLOW_FDM_VIEW = 'cashflow_fdm_view';

    /** RoleService cache keys (v2 + transitional v1) — keep in sync with RoleService::clearCache(). */
    private const ROLE_SERVICE_CACHE_KEYS = [
        'roles.permissions_mapping.v2.super',
        'roles.permissions_mapping.v2.normal',
        'roles.permissions_mapping.super',
        'roles.permissions_mapping.normal',
    ];

    /**
     * Parent group => [base sort_order, [ [child name, child title], ... ]].
     * Names/titles are verbatim from:
     *   - 2026_04_28_080000_add_management_dashboard_tab_permissions.php (overview/practitioners/marketing)
     *   - 2026_04_28_090000_add_dashboard_fdm_permissions.php (fdm)
     */
    private function catalog(): array
    {
        return [
            'dashboard_overview' => [
                'title' => 'Dashboard - Overview',
                'sort_order' => 901,
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
            'dashboard_practitioners' => [
                'title' => 'Dashboard - Practitioners',
                'sort_order' => 902,
                'children' => [
                    ['dashboard.practitioners.list.view', 'Practitioner List'],
                ],
            ],
            'dashboard_marketing' => [
                'title' => 'Dashboard - Marketing',
                'sort_order' => 903,
                'children' => [
                    ['dashboard.marketing.service_interest.view', 'Service Interest'],
                    ['dashboard.marketing.lead_conversion.view', 'Lead Conversion'],
                    ['dashboard.marketing.service_category_trend.view', 'Service Category Trend'],
                    ['dashboard.marketing.sales_concentration.view', 'Sales Concentration'],
                ],
            ],
            'dashboard_fdm' => [
                'title' => 'Dashboard - FDM',
                'sort_order' => 904,
                'children' => [
                    ['dashboard.fdm.cash.view', 'Cash (Live + Week)'],
                    ['dashboard.fdm.today_activities.view', "Today's Activities"],
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
                ],
            ],
        ];
    }

    public function run(): void
    {
        $fdmPanelPerms = [];
        $allPanelPerms = [];

        DB::transaction(function () use (&$fdmPanelPerms, &$allPanelPerms): void {
            // Guarantee the umbrella `management_dashboard` parent + its `view`
            // child exist (owned by migration 2026_04_14_120300, which may be
            // pending in some environments). The `/api/management-dashboard/*`
            // route group is gated by `can:management_dashboard.view`, so every
            // FDM-tab panel API call 403s without this permission — even though
            // the per-panel `dashboard.fdm.*` perms are granted below. Creating
            // it here keeps the seeder self-healing.
            $mdParent = Permission::updateOrCreate(
                ['name' => 'management_dashboard'],
                [
                    'title' => 'Management Dashboard',
                    'main_group' => 1,
                    'parent_id' => 0,
                    'status' => 1,
                    'category' => 'Dashboard',
                    'guard_name' => self::GUARD,
                ],
            );
            Permission::updateOrCreate(
                ['name' => 'management_dashboard.view'],
                [
                    'title' => 'View Dashboard',
                    'main_group' => 0,
                    'parent_id' => $mdParent->id,
                    'status' => 1,
                    'guard_name' => self::GUARD,
                ],
            );

            foreach ($this->catalog() as $parentName => $def) {
                $parent = Permission::updateOrCreate(
                    ['name' => $parentName],
                    [
                        'title' => $def['title'],
                        'main_group' => 1,
                        'parent_id' => 0,
                        'status' => 1,
                        'category' => 'Dashboard',
                        'guard_name' => self::GUARD,
                        'sort_order' => $def['sort_order'],
                    ],
                );

                foreach ($def['children'] as $i => [$name, $title]) {
                    Permission::updateOrCreate(
                        ['name' => $name],
                        [
                            'title' => $title,
                            'main_group' => 0,
                            'parent_id' => $parent->id,
                            'status' => 1,
                            'category' => null,
                            'guard_name' => self::GUARD,
                            'sort_order' => $i + 1,
                        ],
                    );

                    $allPanelPerms[] = $name;
                    if ($parentName === 'dashboard_fdm') {
                        $fdmPanelPerms[] = $name;
                    }
                }
            }

            // Admin roles: every panel (preserves "admin sees everything") +
            // the umbrella `management_dashboard.view` gate the route group needs.
            foreach (Role::whereIn('name', self::ADMIN_ROLES)->get() as $role) {
                $role->givePermissionTo($allPanelPerms);
                $role->givePermissionTo('management_dashboard.view');
            }

            // FDM role: the purpose-built FDM tab only. The SPA auto-hides the
            // other tabs, so this becomes the role's default dashboard view.
            // `management_dashboard.view` is also granted — without it the
            // route-group middleware 403s every panel call regardless of the
            // per-panel grants below (they're per-panel UX gates, not the
            // backend route gate).
            $fdm = Role::where('name', 'FDM')->where('guard_name', self::GUARD)->first();
            if ($fdm) {
                $fdm->givePermissionTo($fdmPanelPerms);
                $fdm->givePermissionTo('management_dashboard.view');
            } else {
                $this->command?->warn('FDM role not found — skipped FDM grant. Assign dashboard.fdm.* via the role editor.');
            }

            // The FDM dashboard's Cash panel calls /api/cashflow/fdm/data,
            // gated by the cashflow module's `cashflow_fdm_view`. That perm
            // is created only by the (unregistered) CashflowPermissionsSeeder
            // and granted to no role, so without this the FDM cash widget is
            // dead (and CheckPermission 403s the XHR). Ensure the gate exists
            // and grant it to FDM + admins. parent_id is role-editor grouping
            // only — Gate::allows() just needs the row + the assignment.
            $cashflowParentId = (int) (Permission::where('name', 'cashflow_manage')->value('id') ?? 0);
            Permission::updateOrCreate(
                ['name' => self::CASHFLOW_FDM_VIEW],
                [
                    'title' => 'View FDM Screen (Branch Cash)',
                    'main_group' => 0,
                    'parent_id' => $cashflowParentId,
                    'status' => 1,
                    'category' => null,
                    'guard_name' => self::GUARD,
                    'sort_order' => 2,
                ],
            );
            foreach (Role::whereIn('name', self::ADMIN_ROLES)->get() as $role) {
                $role->givePermissionTo(self::CASHFLOW_FDM_VIEW);
            }
            if ($fdm) {
                $fdm->givePermissionTo(self::CASHFLOW_FDM_VIEW);
            }

            // Invalidate caches inside the txn so a rollback also rolls back eviction.
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            foreach (self::ROLE_SERVICE_CACHE_KEYS as $key) {
                Cache::forget($key);
            }
        });

        $this->command?->info(sprintf(
            'Dashboard tab permissions seeded: 4 parents + %d panels. FDM granted %d fdm.* perms '
            . '+ cashflow_fdm_view (FDM cash panel). Spatie + RoleService caches cleared. '
            . 'Affected users must re-login or refresh /api/user.',
            count($allPanelPerms),
            count($fdmPanelPerms),
        ));
    }
}
