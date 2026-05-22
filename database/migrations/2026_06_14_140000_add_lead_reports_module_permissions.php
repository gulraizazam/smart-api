<?php

declare(strict_types=1);

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Lead / conversion reports — first Reports-category batch.
 *
 * Four umbrella-only legacy groups normalised to `<module>.view` perms.
 * Each report is read-only and currently gated by a single `_manage`
 * row in the legacy DB (no children, no export-specific perms), so the
 * dotted catalog matches with one `.view` perm per module.
 *
 * Catalog (4 group heads + 4 children):
 *   leads_reports.view              — Lead Reports landing
 *   conversion_report.view          — Doctor's Conversion Report
 *   non_converted_customers.view    — Arrived but not converted
 *   csr_dashboard.view              — CSR Performance dashboard
 *
 * Mirror (legacy → new):
 *   leads_reports_manage           → leads_reports.view
 *   conversion_report_manage       → conversion_report.view
 *   non_converted_customers_manage → non_converted_customers.view
 *   csr_dashboard_report           → csr_dashboard.view
 *
 * If/when an export action gets its own perm, add a follow-up migration
 * that introduces `<module>.export` rows. Out of scope here — legacy
 * had no export gates for any of these four.
 *
 * IMPORTANT — partial migration: legacy rows stay at status=1 because
 * `ArrivedNotConvertedController`, `CsrDashboardController`,
 * `ConversionReportApiController` still call the underscore names.
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
     * @return array<string, array{title: string, child_name: string, child_title: string, legacy: string}>
     */
    private function modules(): array
    {
        return [
            'leads_reports' => [
                'title' => 'Lead Reports',
                'child_name' => 'leads_reports.view',
                'child_title' => 'View Lead Reports',
                'legacy' => 'leads_reports_manage',
            ],
            'conversion_report' => [
                'title' => "Doctor's Conversion Report",
                'child_name' => 'conversion_report.view',
                'child_title' => 'View Conversion Report',
                'legacy' => 'conversion_report_manage',
            ],
            'non_converted_customers' => [
                'title' => 'Non Converted Customers Report',
                'child_name' => 'non_converted_customers.view',
                'child_title' => 'View Non-Converted Customers',
                'legacy' => 'non_converted_customers_manage',
            ],
            'csr_dashboard' => [
                'title' => 'CSR Performance Report',
                'child_name' => 'csr_dashboard.view',
                'child_title' => 'View CSR Dashboard',
                'legacy' => 'csr_dashboard_report',
            ],
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

            // Per-module mirror: each legacy perm → matching .view.
            foreach ($this->modules() as $def) {
                $legacy = DB::table('permissions')->where('name', $def['legacy'])->first();
                if ($legacy === null) {
                    continue;
                }
                $newId = (int) DB::table('permissions')->where('name', $def['child_name'])->value('id');
                if ($newId <= 0) {
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
