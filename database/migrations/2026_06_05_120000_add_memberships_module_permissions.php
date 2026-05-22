<?php

declare(strict_types=1);

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Memberships module — fine-grained permission catalog (`memberships.<action>`).
 *
 * Adds 11 new permissions plus a Catalog-section group head for the SPA
 * `/memberships` page (global membership list — patient-card "Membership"
 * tab keeps its own `patients.membership.assign` / `.cancel` catalog,
 * already audited in the Patients module).
 *
 * Catalog (group head `memberships` + 11 children):
 *   - memberships.list.view        datatable + index page
 *   - memberships.detail.view      single membership / view-details modal
 *   - memberships.create
 *   - memberships.edit
 *   - memberships.destroy
 *   - memberships.activate
 *   - memberships.deactivate
 *   - memberships.sort             reorder rows
 *   - memberships.import           Excel import
 *   - memberships.export           Excel / PDF export
 *   - memberships.codes.manage     bulk generate / assign / revoke codes
 *
 * Mirror grants (no role loses access on day 1):
 *   memberships_manage         → memberships.list.view, memberships.detail.view
 *   memberships_create         → memberships.create
 *   memberships_edit           → memberships.edit
 *   memberships_destroy        → memberships.destroy
 *   memberships_active         → memberships.activate
 *   memberships_inactive       → memberships.deactivate
 *   memberships_sort           → memberships.sort
 *   memberships_import         → memberships.import
 *   memberships_export         → memberships.export
 *   membership_codes_manage    → memberships.codes.manage
 *
 * Companion migration `2026_06_05_130000_hide_legacy_memberships_group`
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
            ['name' => 'memberships.list.view',     'title' => 'View list'],
            ['name' => 'memberships.detail.view',   'title' => 'View membership detail'],
            ['name' => 'memberships.create',        'title' => 'Create'],
            ['name' => 'memberships.edit',          'title' => 'Edit'],
            ['name' => 'memberships.destroy',       'title' => 'Delete'],
            ['name' => 'memberships.activate',      'title' => 'Activate'],
            ['name' => 'memberships.deactivate',    'title' => 'Deactivate'],
            ['name' => 'memberships.sort',          'title' => 'Reorder rows'],
            ['name' => 'memberships.import',        'title' => 'Import from Excel'],
            ['name' => 'memberships.export',        'title' => 'Export to Excel / PDF'],
            ['name' => 'memberships.codes.manage',  'title' => 'Manage membership codes'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function legacyToNewMap(): array
    {
        return [
            'memberships_manage'      => ['memberships.list.view', 'memberships.detail.view'],
            'memberships_create'      => ['memberships.create'],
            'memberships_edit'        => ['memberships.edit'],
            'memberships_destroy'     => ['memberships.destroy'],
            'memberships_active'      => ['memberships.activate'],
            'memberships_inactive'    => ['memberships.deactivate'],
            'memberships_sort'        => ['memberships.sort'],
            'memberships_import'      => ['memberships.import'],
            'memberships_export'      => ['memberships.export'],
            'membership_codes_manage' => ['memberships.codes.manage'],
        ];
    }

    public function up(): void
    {
        DB::transaction(function (): void {
            $group = Permission::updateOrCreate(
                ['name' => 'memberships'],
                [
                    'title' => 'Memberships',
                    'main_group' => 1,
                    'parent_id' => 0,
                    'status' => 1,
                    'category' => 'Catalog',
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

            Permission::where('name', 'memberships')
                ->where('main_group', 1)
                ->where('category', 'Catalog')
                ->delete();

            app(PermissionRegistrar::class)->forgetCachedPermissions();
            foreach (self::ROLE_SERVICE_CACHE_KEYS as $key) {
                Cache::forget($key);
            }
        });
    }
};
