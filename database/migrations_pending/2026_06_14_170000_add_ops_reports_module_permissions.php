<?php

declare(strict_types=1);

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Operations / centre / inventory reports — final Reports-category
 * batch. Closes out the dotted-permissions migration across all
 * categories.
 *
 * Seven legacy groups, ranging from umbrella-only to sub-report-heavy:
 *   - 5 umbrella-only: centers / marketing / follow_up /
 *     followuppatient (renamed to future_treatments_reports) / inventory_report
 *   - 2 multi-child:   appointment_reports (10 subs) +
 *                       operations_reports (12 subs)
 *
 * Catalog (7 group heads + 30 children).
 *
 * Naming normalisations applied in the dotted catalog (legacy mirror
 * keeps the misspellings working through the cutover):
 *   - `empolyee_summary` → `employee_summary`   (typo fix)
 *   - `complimentory_report` → `complimentary_report` (typo fix)
 *   - `List_of_services_that_CAN_not_be_offered_Complimentary` → lowercase
 *     `list_of_services_that_can_not_be_offered_complimentary` (and the
 *     positive twin) — drops the CamelCase quirk from the original perm
 *     names.
 *   - `followuppatient_manage` (no spaces / unclear) →
 *     `future_treatments_reports.view` (matches the legacy title).
 *
 * Mirror grants are 1-to-1 against the legacy underscore catalog.
 *
 * IMPORTANT — partial migration: legacy rows stay at status=1 because
 * `Admin\Reports\AppointmentsController`, `Admin\Reports\OperationsReportController`,
 * `FollowUpReportApiController`, `FutureTreatmentsReportApiController`,
 * `InventoryReportsApiController` still call the underscore names.
 * Companion hide-legacy migration runs after the controller rewrite.
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
            'centers_reports' => [
                'title' => 'Centre Reports',
                'children' => [
                    ['name' => 'centers_reports.view', 'title' => 'View Centre Reports'],
                ],
            ],
            'marketing_reports' => [
                'title' => 'Marketing Reports',
                'children' => [
                    ['name' => 'marketing_reports.view', 'title' => 'View Marketing Reports'],
                ],
            ],
            'follow_up' => [
                'title' => 'Follow Up Report (Unattended and Overdue Treatments)',
                'children' => [
                    ['name' => 'follow_up.view', 'title' => 'View Follow-up Report'],
                ],
            ],
            'future_treatments_reports' => [
                'title' => 'Future Treatments Reports',
                'children' => [
                    ['name' => 'future_treatments_reports.view', 'title' => 'View Future Treatments Reports'],
                ],
            ],
            'inventory_report' => [
                'title' => 'Inventory Reports',
                'children' => [
                    ['name' => 'inventory_report.view', 'title' => 'View Inventory Reports'],
                ],
            ],
            'appointment_reports' => [
                'title' => 'Appointment Reports',
                'children' => [
                    ['name' => 'appointment_reports.view',                              'title' => 'View landing'],
                    ['name' => 'appointment_reports.general_report',                    'title' => 'General Report'],
                    ['name' => 'appointment_reports.compliance_reports',                'title' => 'Compliance Report'],
                    ['name' => 'appointment_reports.clients_by_appointment_status',     'title' => 'Patient by Appointment Status (Weekly)'],
                    ['name' => 'appointment_reports.summary_by_appointment_status',     'title' => 'Appointments Summary by Status'],
                    ['name' => 'appointment_reports.summary_by_service',                'title' => 'Appointments Summary by Service'],
                    ['name' => 'appointment_reports.employee_summary',                  'title' => 'Appointment Summary Report'],
                    ['name' => 'appointment_reports.referred_by_staff_appointment',     'title' => 'Staff Wise (red) Appointment Report'],
                    ['name' => 'appointment_reports.general_summary_report',            'title' => 'General Report Summary'],
                    ['name' => 'appointment_reports.staff_appointment',                 'title' => 'Staff Wise Appointment Report'],
                    ['name' => 'appointment_reports.rescheduled_count_report',          'title' => 'Appointment Rescheduled Count Report'],
                ],
            ],
            'operations_reports' => [
                'title' => 'Operation Reports',
                'children' => [
                    ['name' => 'operations_reports.view',                                              'title' => 'View landing'],
                    ['name' => 'operations_reports.dtr_report',                                        'title' => 'DTR Report'],
                    ['name' => 'operations_reports.complimentary_report',                              'title' => 'Complimentary Treatment Report'],
                    ['name' => 'operations_reports.dar_report',                                        'title' => 'DAR Report'],
                    ['name' => 'operations_reports.conversion_report_treatment',                       'title' => 'Conversion Report For Treatment'],
                    ['name' => 'operations_reports.conversion_report_consultancy',                     'title' => 'Conversion Report For Consultancy'],
                    ['name' => 'operations_reports.list_of_services_that_can_not_be_offered_complimentary', 'title' => 'List of services that CAN NOT be offered Complimentary'],
                    ['name' => 'operations_reports.list_of_services_that_can_be_offered_complimentary', 'title' => 'List of services that CAN be offered Complimentary'],
                    ['name' => 'operations_reports.list_of_refunds_for_a_certain_period_date_based',   'title' => 'List of refunds for a certain period (date based)'],
                    ['name' => 'operations_reports.highest_paying_clients',                            'title' => 'Highest Paying Clients'],
                    ['name' => 'operations_reports.operations_company_health',                         'title' => 'Company Health Report'],
                    ['name' => 'operations_reports.operations_tax_calculation_report',                 'title' => 'Tax Calculation Report'],
                    ['name' => 'operations_reports.center_target_report',                              'title' => 'Center Target Report'],
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
            'centers_reports_manage'   => ['centers_reports.view'],
            'marketing_reports_manage' => ['marketing_reports.view'],
            'follow_up_manage'         => ['follow_up.view'],
            'followuppatient_manage'   => ['future_treatments_reports.view'],
            'inventory_report_manage'  => ['inventory_report.view'],

            'appointment_reports_manage'                       => ['appointment_reports.view'],
            'appointment_reports_general_report'               => ['appointment_reports.general_report'],
            'appointment_reports_compliance_reports'           => ['appointment_reports.compliance_reports'],
            'appointment_reports_clients_by_appointment_status' => ['appointment_reports.clients_by_appointment_status'],
            'appointment_reports_summary_by_appointment_status' => ['appointment_reports.summary_by_appointment_status'],
            'appointment_reports_summary_by_service'           => ['appointment_reports.summary_by_service'],
            // Typo fix: legacy `empolyee_summary` → dotted `employee_summary`.
            'appointment_reports_empolyee_summary'             => ['appointment_reports.employee_summary'],
            'appointment_reports_referred_by_staff_appointment' => ['appointment_reports.referred_by_staff_appointment'],
            'appointment_reports_general_summary_report'       => ['appointment_reports.general_summary_report'],
            'appointment_reports_staff_appointment'            => ['appointment_reports.staff_appointment'],
            'appointment_reports_rescheduled_count_report'     => ['appointment_reports.rescheduled_count_report'],

            'operations_reports_manage'                                              => ['operations_reports.view'],
            'operations_reports_dtr_report'                                          => ['operations_reports.dtr_report'],
            // Typo fix: `complimentory` → `complimentary`.
            'operations_reports_complimentory_report'                                => ['operations_reports.complimentary_report'],
            'operations_reports_dar_report'                                          => ['operations_reports.dar_report'],
            'operations_reports_conversion_report_treatment'                         => ['operations_reports.conversion_report_treatment'],
            'operations_reports_conversion_report_consultancy'                       => ['operations_reports.conversion_report_consultancy'],
            // Casing fix: drop the CamelCase fragments in the legacy names.
            'operations_reports_List_of_services_that_CAN_not_be_offered_Complimentary' => ['operations_reports.list_of_services_that_can_not_be_offered_complimentary'],
            'operations_reports_List_of_services_that_CAN_be_offered_Complimentary'     => ['operations_reports.list_of_services_that_can_be_offered_complimentary'],
            'operations_reports_List_of_refunds_for_a_certain_period_date_based'        => ['operations_reports.list_of_refunds_for_a_certain_period_date_based'],
            'operations_reports_Highest_paying_clients'                              => ['operations_reports.highest_paying_clients'],
            'operations_reports_operations_company_health'                           => ['operations_reports.operations_company_health'],
            'operations_reports_operations_tax_calculation_report'                   => ['operations_reports.operations_tax_calculation_report'],
            'operations_reports_center_target_report'                                => ['operations_reports.center_target_report'],
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
