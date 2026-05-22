<?php

declare(strict_types=1);

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Voucher Types module — fine-grained permission catalog
 * (`voucher_types.<action>`).
 *
 * Adds 9 new permissions plus a Catalog-section group head for the SPA
 * `/voucher-types` page (managed in Admin\VouchersController + VoucherTypeService;
 * the legacy DB rows live on the `discounts` table with `discount_type='voucher'`).
 *
 * Why this module needed a heavy hand: the seeder only ever created the
 * `voucher_types_manage` umbrella — `voucher_types_{create,edit,destroy,active,
 * inactive,allocate,assign}` were referenced by gate calls but were never
 * seeded as rows, so they only resolved truthy via wildcard for admins.
 * The companion code rewrite re-points every gate at the new dotted
 * names so non-admin roles can actually be granted these actions.
 *
 * Catalog (group head `voucher_types` + 9 children):
 *   - voucher_types.list.view              datatable + index page
 *   - voucher_types.list.view_inactive     filter inactive voucher types in the list
 *   - voucher_types.create
 *   - voucher_types.edit
 *   - voucher_types.destroy
 *   - voucher_types.activate
 *   - voucher_types.deactivate
 *   - voucher_types.allocate               allocate to centres / services
 *   - voucher_types.assign                 assign a voucher to a patient
 *
 * Mirror grants (no role loses access on day 1):
 *   voucher_types_manage    → voucher_types.list.view
 *   voucher_types_create    → voucher_types.create
 *   voucher_types_edit      → voucher_types.edit
 *   voucher_types_active    → voucher_types.activate
 *   voucher_types_inactive  → voucher_types.deactivate
 *   voucher_types_destroy   → voucher_types.destroy
 *   voucher_types_allocate  → voucher_types.allocate
 *   voucher_types_assign    → voucher_types.assign
 *
 * Companion migration `2026_06_02_130000_hide_legacy_voucher_types_group`
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
            ['name' => 'voucher_types.list.view',           'title' => 'View list'],
            ['name' => 'voucher_types.list.view_inactive',  'title' => 'See inactive voucher types'],
            ['name' => 'voucher_types.create',              'title' => 'Create'],
            ['name' => 'voucher_types.edit',                'title' => 'Edit'],
            ['name' => 'voucher_types.destroy',             'title' => 'Delete'],
            ['name' => 'voucher_types.activate',            'title' => 'Activate'],
            ['name' => 'voucher_types.deactivate',          'title' => 'Deactivate'],
            ['name' => 'voucher_types.allocate',            'title' => 'Allocate to centres / services'],
            ['name' => 'voucher_types.assign',              'title' => 'Assign to patient'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function legacyToNewMap(): array
    {
        return [
            'voucher_types_manage'   => ['voucher_types.list.view'],
            'voucher_types_create'   => ['voucher_types.create'],
            'voucher_types_edit'     => ['voucher_types.edit'],
            'voucher_types_active'   => ['voucher_types.activate'],
            'voucher_types_inactive' => ['voucher_types.deactivate'],
            'voucher_types_destroy'  => ['voucher_types.destroy'],
            'voucher_types_allocate' => ['voucher_types.allocate'],
            'voucher_types_assign'   => ['voucher_types.assign'],
        ];
    }

    public function up(): void
    {
        DB::transaction(function (): void {
            $group = Permission::updateOrCreate(
                ['name' => 'voucher_types'],
                [
                    'title' => 'Voucher Types',
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

            // Only voucher_types_manage was actually seeded historically;
            // the rest of legacyToNewMap() will silently no-op via the
            // `! isset($perms[$legacy])` guard below.
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

            Permission::where('name', 'voucher_types')
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
