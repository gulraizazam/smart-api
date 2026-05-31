<?php

declare(strict_types=1);

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Finance reports — third Reports-category batch.
 *
 * Two umbrella-only legacy groups (revenue breakup + ledger) plus the
 * `finance_general_revenue_reports` family which has 16 sub-report
 * children. Each sub-report controller gates on its own legacy perm
 * (`finance_general_revenue_reports_<sub>`), so the dotted catalog
 * preserves the sub-naming under a single group head and normalises
 * the double-underscore quirks in two of the rows.
 *
 * Catalog (3 group heads + 19 children):
 *   finance_revenue_breakup_reports.view
 *   finance_ledger_reports.view
 *   finance_general_revenue_reports (17 children)
 *     - .view                                          umbrella / landing
 *     - .daily_employee_stats
 *     - .daily_employee_stats_summary
 *     - .machine_wise_collection_report
 *     - .machine_wise_invoice_revenue_report
 *     - .conversion_report
 *     - .partner_collection_report
 *     - .pabau_record_revenue_report
 *     - .discount_report
 *     - .sales_by_service_category
 *     - .center_performance_stats_by_service_type_finance
 *     - .center_performance_stats_by_revenue_finance
 *     - .collection_by_service
 *     - .staff_wise_revenue
 *     - .general_revenue_summary_report   (legacy: general_revenue__summary_report)
 *     - .general_revenue_detail_report    (legacy: general_revenue__detail_report)
 *     - .consume_plan_revenue_report
 *
 * Mirror is 1-to-1 for the 19 sub-perms + 3 group-level umbrellas.
 * Two legacy rows have a double underscore between `revenue` and
 * `summary` / `detail` — the dotted catalog normalises that to a
 * single underscore.
 *
 * IMPORTANT — partial migration: legacy rows stay at status=1 because
 * `FinanceCollectionReportController`, `FinanceRevenueReportController`,
 * `FinancePlanReportController`, `FinanceStaffReportController` still
 * call the underscore names. Companion hide-legacy migration runs
 * after the controller rewrite.
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
     * @return array<string, array{title: string, children: list<array{name: string, title: string}>}>
     */
    private function modules(): array
    {
        return [
            'finance_revenue_breakup_reports' => [
                'title' => 'Finance Revenue Breakup Reports',
                'children' => [
                    ['name' => 'finance_revenue_breakup_reports.view', 'title' => 'View Revenue Breakup Reports'],
                ],
            ],
            'finance_ledger_reports' => [
                'title' => 'Finance Ledger Reports',
                'children' => [
                    ['name' => 'finance_ledger_reports.view', 'title' => 'View Ledger Reports'],
                ],
            ],
            'finance_general_revenue_reports' => [
                'title' => 'Finance General Revenue Reports',
                'children' => [
                    ['name' => 'finance_general_revenue_reports.view',                                          'title' => 'View General Revenue landing'],
                    ['name' => 'finance_general_revenue_reports.daily_employee_stats',                          'title' => 'Sale Summary Doctors Wise'],
                    ['name' => 'finance_general_revenue_reports.daily_employee_stats_summary',                  'title' => 'Sale Summary Service Wise'],
                    ['name' => 'finance_general_revenue_reports.machine_wise_collection_report',                'title' => 'Machine wise Collection Report'],
                    ['name' => 'finance_general_revenue_reports.machine_wise_invoice_revenue_report',           'title' => 'Machine wise Invoice Revenue Report'],
                    ['name' => 'finance_general_revenue_reports.conversion_report',                             'title' => 'Conversion Report'],
                    ['name' => 'finance_general_revenue_reports.partner_collection_report',                     'title' => 'Partner Collection Report'],
                    ['name' => 'finance_general_revenue_reports.pabau_record_revenue_report',                   'title' => 'Pabau Record Revenue'],
                    ['name' => 'finance_general_revenue_reports.discount_report',                               'title' => 'Discount Report'],
                    ['name' => 'finance_general_revenue_reports.sales_by_service_category',                     'title' => 'Sale Summary Category Wise'],
                    ['name' => 'finance_general_revenue_reports.center_performance_stats_by_service_type_finance', 'title' => 'Center performance stats by Service Type'],
                    ['name' => 'finance_general_revenue_reports.center_performance_stats_by_revenue_finance',   'title' => 'Center performance stats by Revenue'],
                    ['name' => 'finance_general_revenue_reports.collection_by_service',                         'title' => 'Collection by Service'],
                    ['name' => 'finance_general_revenue_reports.staff_wise_revenue',                            'title' => 'Staff Wise Revenue'],
                    ['name' => 'finance_general_revenue_reports.general_revenue_summary_report',                'title' => 'General Revenue Report Summary'],
                    ['name' => 'finance_general_revenue_reports.general_revenue_detail_report',                 'title' => 'General Revenue Report Detail'],
                    ['name' => 'finance_general_revenue_reports.consume_plan_revenue_report',                   'title' => 'Consume Plan Revenue Report'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function legacyToNewMap(): array
    {
        return [
            'finance_revenue_breakup_reports_manage' => ['finance_revenue_breakup_reports.view'],
            'finance_ledger_reports_manage'          => ['finance_ledger_reports.view'],

            // General revenue family — verbose 1-to-1 mapping. Note the
            // two double-underscore legacy names that normalise to
            // single-underscore dotted names.
            'finance_general_revenue_reports_manage'                                          => ['finance_general_revenue_reports.view'],
            'finance_general_revenue_reports_daily_employee_stats'                            => ['finance_general_revenue_reports.daily_employee_stats'],
            'finance_general_revenue_reports_daily_employee_stats_summary'                    => ['finance_general_revenue_reports.daily_employee_stats_summary'],
            'finance_general_revenue_reports_machine_wise_collection_report'                  => ['finance_general_revenue_reports.machine_wise_collection_report'],
            'finance_general_revenue_reports_machine_wise_invoice_revenue_report'             => ['finance_general_revenue_reports.machine_wise_invoice_revenue_report'],
            'finance_general_revenue_reports_conversion_report'                               => ['finance_general_revenue_reports.conversion_report'],
            'finance_general_revenue_reports_partner_collection_report'                       => ['finance_general_revenue_reports.partner_collection_report'],
            'finance_general_revenue_reports_pabau_record_revenue_report'                     => ['finance_general_revenue_reports.pabau_record_revenue_report'],
            'finance_general_revenue_reports_discount_report'                                 => ['finance_general_revenue_reports.discount_report'],
            'finance_general_revenue_reports_sales_by_service_category'                       => ['finance_general_revenue_reports.sales_by_service_category'],
            'finance_general_revenue_reports_center_performance_stats_by_service_type_finance' => ['finance_general_revenue_reports.center_performance_stats_by_service_type_finance'],
            'finance_general_revenue_reports_center_performance_stats_by_revenue_finance'     => ['finance_general_revenue_reports.center_performance_stats_by_revenue_finance'],
            'finance_general_revenue_reports_collection_by_service'                           => ['finance_general_revenue_reports.collection_by_service'],
            'finance_general_revenue_reports_staff_wise_revenue'                              => ['finance_general_revenue_reports.staff_wise_revenue'],
            'finance_general_revenue_reports_general_revenue__summary_report'                 => ['finance_general_revenue_reports.general_revenue_summary_report'],
            'finance_general_revenue_reports_general_revenue__detail_report'                  => ['finance_general_revenue_reports.general_revenue_detail_report'],
            'finance_general_revenue_reports_consume_plan_revenue_report'                     => ['finance_general_revenue_reports.consume_plan_revenue_report'],
        ];
    }

    public function up(): void
    {
        DB::transaction(function (): void {
            $allNewNames = [];

            foreach ($this->modules() as $groupName => $def) {
                $group = Permission::updateOrCreate(
                    ['name' => $groupName],
                    [
                        'title' => $def['title'],
                        'main_group' => 1,
                        'parent_id' => 0,
                        'status' => 1,
                        'category' => 'Reports',
                        'guard_name' => self::GUARD,
                    ],
                );

                foreach ($def['children'] as $i => $row) {
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
                    $allNewNames[] = $row['name'];
                }
            }

            foreach (Role::whereIn('name', self::ADMIN_ROLES)->get() as $role) {
                $role->givePermissionTo($allNewNames);
            }

            $perms = DB::table('permissions')
                ->whereIn('name', array_keys($this->legacyToNewMap()))
                ->get(['id', 'name'])
                ->keyBy('name');

            $newPermIds = DB::table('permissions')
                ->whereIn('name', $allNewNames)
                ->pluck('id', 'name');

            foreach ($this->legacyToNewMap() as $legacy => $news) {
                if (! isset($perms[$legacy])) {
                    continue;
                }
                $legacyId = (int) $perms[$legacy]->id;
                $rolesWithLegacy = DB::table('role_has_permissions')
                    ->where('permission_id', $legacyId)
                    ->pluck('role_id');
                $usersWithLegacy = DB::table('model_has_permissions')
                    ->where('permission_id', $legacyId)
                    ->get(['model_id', 'model_type']);

                foreach ($news as $newName) {
                    $newId = (int) ($newPermIds[$newName] ?? 0);
                    if ($newId <= 0) {
                        continue;
                    }
                    foreach ($rolesWithLegacy as $roleId) {
                        DB::table('role_has_permissions')->updateOrInsert(
                            ['role_id' => $roleId, 'permission_id' => $newId],
                            [],
                        );
                    }
                    foreach ($usersWithLegacy as $assoc) {
                        DB::table('model_has_permissions')->updateOrInsert(
                            [
                                'permission_id' => $newId,
                                'model_id'      => $assoc->model_id,
                                'model_type'    => $assoc->model_type,
                            ],
                            [],
                        );
                    }
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
            $allNames = [];
            foreach ($this->modules() as $def) {
                foreach ($def['children'] as $row) {
                    $allNames[] = $row['name'];
                }
            }

            Permission::whereIn('name', $allNames)->delete();
            Permission::whereIn('name', array_keys($this->modules()))
                ->where('main_group', 1)
                ->where('category', 'Reports')
                ->delete();

            app(PermissionRegistrar::class)->forgetCachedPermissions();
            foreach (self::ROLE_SERVICE_CACHE_KEYS as $key) {
                Cache::forget($key);
            }
        });
    }
};
