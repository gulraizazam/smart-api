<?php

declare(strict_types=1);

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Invoices module — fine-grained permission catalog (`invoices.<action>`).
 *
 * Adds 10 new permissions plus a Finance-section group head for the SPA
 * `/invoices` page. Splits the legacy `invoices_manage` umbrella into
 * separate list/detail/edit/print perms so a role can hold one without
 * the others.
 *
 * NOT touched here (other modules own them):
 *   - `appointments_invoice` / `appointments_invoice_display`
 *     → already mapped to `consultations.invoice.create` / `.display`
 *       in the Consultations audit (migration 2026_05_22_120000).
 *   - `patient_invoices` / `patients_invoice_*` → patient-card scope
 *       (Patients audit, migration 2026_05_20_120000).
 *   - `consultancy_invoice` / `consultancy_invoice_display` → Consultations.
 *
 * Catalog (group head `invoices` + 10 children):
 *   - invoices.list.view       datatable + index page
 *   - invoices.detail.view     single invoice detail / show
 *   - invoices.create
 *   - invoices.edit
 *   - invoices.destroy
 *   - invoices.cancel          void / cancel invoice
 *   - invoices.export          spreadsheet export
 *   - invoices.print           PDF download
 *   - invoices.log.view        audit / activity log
 *   - invoices.sms_log.view    SMS log history
 *
 * Mirror grants (no role loses access on day 1):
 *   invoices_manage    → invoices.list.view, invoices.detail.view, invoices.edit, invoices.print
 *   invoices_create    → invoices.create
 *   invoices_destroy   → invoices.destroy
 *   invoices_export    → invoices.export
 *   invoices_cancel    → invoices.cancel
 *   invoices_log       → invoices.log.view
 *   invoices_sms_log   → invoices.sms_log.view
 *
 * Companion migration `2026_06_06_130000_hide_legacy_invoices_group`
 * flips the legacy rows to status=0 once the rewrite lands.
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
     * @return list<array{name: string, title: string}>
     */
    private function newPerms(): array
    {
        return [
            ['name' => 'invoices.list.view',     'title' => 'View list'],
            ['name' => 'invoices.detail.view',   'title' => 'View invoice detail'],
            ['name' => 'invoices.create',        'title' => 'Create'],
            ['name' => 'invoices.edit',          'title' => 'Edit'],
            ['name' => 'invoices.destroy',       'title' => 'Delete'],
            ['name' => 'invoices.cancel',        'title' => 'Cancel / void'],
            ['name' => 'invoices.export',        'title' => 'Export to spreadsheet'],
            ['name' => 'invoices.print',         'title' => 'Print PDF'],
            ['name' => 'invoices.log.view',      'title' => 'View audit log'],
            ['name' => 'invoices.sms_log.view',  'title' => 'View SMS log'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function legacyToNewMap(): array
    {
        return [
            'invoices_manage'  => ['invoices.list.view', 'invoices.detail.view', 'invoices.edit', 'invoices.print'],
            'invoices_create'  => ['invoices.create'],
            'invoices_destroy' => ['invoices.destroy'],
            'invoices_export'  => ['invoices.export'],
            'invoices_cancel'  => ['invoices.cancel'],
            'invoices_log'     => ['invoices.log.view'],
            'invoices_sms_log' => ['invoices.sms_log.view'],
        ];
    }

    public function up(): void
    {
        DB::transaction(function (): void {
            $group = Permission::updateOrCreate(
                ['name' => 'invoices'],
                [
                    'title' => 'Invoices',
                    'main_group' => 1,
                    'parent_id' => 0,
                    'status' => 1,
                    'category' => 'Finance',
                    'guard_name' => self::GUARD,
                ],
            );

            foreach ($this->newPerms() as $i => $row) {
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
            }

            $newNames = array_map(static fn ($r) => $r['name'], $this->newPerms());

            foreach (Role::whereIn('name', self::ADMIN_ROLES)->get() as $role) {
                $role->givePermissionTo($newNames);
            }

            $perms = DB::table('permissions')
                ->whereIn('name', array_keys($this->legacyToNewMap()))
                ->get(['id', 'name'])
                ->keyBy('name');

            $newPermIds = DB::table('permissions')
                ->whereIn('name', $newNames)
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
            $names = array_map(static fn ($r) => $r['name'], $this->newPerms());
            Permission::whereIn('name', $names)->delete();

            Permission::where('name', 'invoices')
                ->where('main_group', 1)
                ->where('category', 'Finance')
                ->delete();

            app(PermissionRegistrar::class)->forgetCachedPermissions();
            foreach (self::ROLE_SERVICE_CACHE_KEYS as $key) {
                Cache::forget($key);
            }
        });
    }
};
