<?php

declare(strict_types=1);

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Staff / HR reports — second Reports-category batch.
 *
 * Six umbrella-only legacy groups normalised to `<module>.view` perms.
 * Same shape as the lead-reports batch — read-only screens, no export
 * gates seeded in legacy.
 *
 * Catalog (6 group heads + 6 children):
 *   hr_reports.view              — HR Reports landing
 *   staff_wise_arrival.view      — Staff Wise Arrival Report
 *   staff_listing_reports.view   — Staff Listing Reports
 *   staff_revenue_reports.view   — Staff Revenue Reports
 *   upselling_report.view        — Upselling Report
 *   consultant_revenue_report.view — Consultant Revenue Report
 *
 * Naming notes:
 *   - Legacy `Hr_reports_manage` has a capital `H` (case anomaly).
 *     The dotted version uses lowercase `hr_reports` — consistent with
 *     the rest of the catalog.
 *   - `upselling_report` and `consultant_revenue_report` have no `_manage`
 *     suffix in legacy; the dotted name keeps the bare module name and
 *     appends `.view`.
 *
 * Mirror (legacy → new):
 *   Hr_reports_manage             → hr_reports.view
 *   staff_wise_arrival_manage     → staff_wise_arrival.view
 *   staff_listing_reports_manage  → staff_listing_reports.view
 *   staff_revenue_reports_manage  → staff_revenue_reports.view
 *   upselling_report              → upselling_report.view
 *   consultant_revenue_report     → consultant_revenue_report.view
 *
 * IMPORTANT — partial migration: legacy rows stay at status=1.
 * `UpsellingApiController` + `DoctorIncentiveReportController` still
 * call the underscore names. Companion hide-legacy migration after the
 * controller rewrite.
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
     * @return array<string, array{title: string, child_name: string, child_title: string, legacy: string}>
     */
    private function modules(): array
    {
        return [
            'hr_reports' => [
                'title' => 'HR Reports',
                'child_name' => 'hr_reports.view',
                'child_title' => 'View HR Reports',
                'legacy' => 'Hr_reports_manage',
            ],
            'staff_wise_arrival' => [
                'title' => 'Staff Wise Arrival Report',
                'child_name' => 'staff_wise_arrival.view',
                'child_title' => 'View Staff Wise Arrival',
                'legacy' => 'staff_wise_arrival_manage',
            ],
            'staff_listing_reports' => [
                'title' => 'Staff Listing Reports',
                'child_name' => 'staff_listing_reports.view',
                'child_title' => 'View Staff Listing',
                'legacy' => 'staff_listing_reports_manage',
            ],
            'staff_revenue_reports' => [
                'title' => 'Staff Revenue Reports',
                'child_name' => 'staff_revenue_reports.view',
                'child_title' => 'View Staff Revenue',
                'legacy' => 'staff_revenue_reports_manage',
            ],
            'upselling_report' => [
                'title' => 'Upselling Report',
                'child_name' => 'upselling_report.view',
                'child_title' => 'View Upselling Report',
                'legacy' => 'upselling_report',
            ],
            'consultant_revenue_report' => [
                'title' => 'Consultant Revenue Report',
                'child_name' => 'consultant_revenue_report.view',
                'child_title' => 'View Consultant Revenue',
                'legacy' => 'consultant_revenue_report',
            ],
        ];
    }

    public function up(): void
    {
        DB::transaction(function (): void {
            $allNewNames = [];

            foreach ($this->modules() as $groupName => $def) {
                // Some legacy rows share the bare module name AND the
                // group-head row (e.g. `upselling_report` is itself a
                // main_group=1 row). updateOrCreate by name is fine
                // because the new group head re-uses the same row but
                // gets its title / status normalised.
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

                Permission::updateOrCreate(
                    ['name' => $def['child_name']],
                    [
                        'title' => $def['child_title'],
                        'main_group' => 0,
                        'parent_id' => $group->id,
                        'status' => 1,
                        'category' => null,
                        'guard_name' => self::GUARD,
                        'sort_order' => 1,
                    ],
                );
                $allNewNames[] = $def['child_name'];
            }

            foreach (Role::whereIn('name', self::ADMIN_ROLES)->get() as $role) {
                $role->givePermissionTo($allNewNames);
            }

            foreach ($this->modules() as $def) {
                $legacy = DB::table('permissions')->where('name', $def['legacy'])->first();
                if ($legacy === null) {
                    continue;
                }
                $newId = (int) DB::table('permissions')->where('name', $def['child_name'])->value('id');
                if ($newId <= 0 || $newId === (int) $legacy->id) {
                    // Skip the self-mirror case (when group head + perm
                    // are the same row, e.g. `upselling_report`).
                    continue;
                }

                $roleIds = DB::table('role_has_permissions')
                    ->where('permission_id', $legacy->id)
                    ->pluck('role_id');
                foreach ($roleIds as $roleId) {
                    DB::table('role_has_permissions')->updateOrInsert(
                        ['role_id' => $roleId, 'permission_id' => $newId],
                        [],
                    );
                }
                $directGrants = DB::table('model_has_permissions')
                    ->where('permission_id', $legacy->id)
                    ->get(['model_id', 'model_type']);
                foreach ($directGrants as $grant) {
                    DB::table('model_has_permissions')->updateOrInsert(
                        [
                            'permission_id' => $newId,
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
            $childNames = array_map(static fn ($def) => $def['child_name'], $this->modules());
            Permission::whereIn('name', $childNames)->delete();

            // Don't delete `upselling_report` / `consultant_revenue_report`
            // group heads — those rows pre-existed (legacy DB) and
            // updateOrCreate just normalised them; deleting would drop
            // legacy grants. Only delete the brand-new group heads.
            Permission::whereIn('name', ['hr_reports', 'staff_wise_arrival', 'staff_listing_reports', 'staff_revenue_reports'])
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
