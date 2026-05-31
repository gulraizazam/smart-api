<?php

declare(strict_types=1);

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Refunds module — fine-grained permission catalog (`refunds.<action>`).
 *
 * Adds 8 new permissions plus a Finance-section group head for the SPA
 * `/refunds` page (global refund list + per-plan refund history + create
 * refund flow). Splits the legacy `refunds_manage` umbrella into separate
 * list/detail perms.
 *
 * NOT touched (other modules own them):
 *   - `patients_refund_manage` / `patients_refund_refund` → patient-card
 *       scope (Patients audit, PatientsSeed).
 *   - `patient_refund` → legacy fallback in InvoicePolicy / PatientPolicy.
 *
 * Catalog (group head `refunds` + 8 children):
 *   - refunds.list.view      datatable + index page
 *   - refunds.detail.view    refund history modal / patient ledger detail
 *   - refunds.create         calculate + create refund
 *   - refunds.edit
 *   - refunds.destroy
 *   - refunds.refund         process the refund action itself
 *   - refunds.activate
 *   - refunds.deactivate
 *
 * Mirror grants (no role loses access on day 1):
 *   refunds_manage     → refunds.list.view, refunds.detail.view
 *   refunds_create     → refunds.create
 *   refunds_edit       → refunds.edit
 *   refunds_destroy    → refunds.destroy
 *   refunds_refund     → refunds.refund
 *   refunds_active     → refunds.activate
 *   refunds_inactive   → refunds.deactivate
 *
 * Companion migration `2026_06_07_130000_hide_legacy_refunds_group`
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
            ['name' => 'refunds.list.view',    'title' => 'View list'],
            ['name' => 'refunds.detail.view',  'title' => 'View refund history / ledger'],
            ['name' => 'refunds.create',       'title' => 'Create (calculate + new)'],
            ['name' => 'refunds.edit',         'title' => 'Edit'],
            ['name' => 'refunds.destroy',      'title' => 'Delete'],
            ['name' => 'refunds.refund',       'title' => 'Process refund'],
            ['name' => 'refunds.activate',     'title' => 'Activate'],
            ['name' => 'refunds.deactivate',   'title' => 'Deactivate'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function legacyToNewMap(): array
    {
        return [
            'refunds_manage'   => ['refunds.list.view', 'refunds.detail.view'],
            'refunds_create'   => ['refunds.create'],
            'refunds_edit'     => ['refunds.edit'],
            'refunds_destroy'  => ['refunds.destroy'],
            'refunds_refund'   => ['refunds.refund'],
            'refunds_active'   => ['refunds.activate'],
            'refunds_inactive' => ['refunds.deactivate'],
        ];
    }

    public function up(): void
    {
        DB::transaction(function (): void {
            $group = Permission::updateOrCreate(
                ['name' => 'refunds'],
                [
                    'title' => 'Refunds',
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

            Permission::where('name', 'refunds')
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
