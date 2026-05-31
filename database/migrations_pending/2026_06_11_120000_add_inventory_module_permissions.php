<?php

declare(strict_types=1);

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Inventory module — fine-grained permission catalog
 * (`inventory.<area>.<action>`).
 *
 * Folds the three legacy group heads (`brand_manage`, `product_manage`,
 * `order_manage`) plus the orphaned `inventory_manage` umbrella into
 * one dotted catalog under category `Inventory`, mirroring the cashflow
 * shape (one group head + sub-area namespaces).
 *
 * Catalog (group head `inventory` + 21 children):
 *   Umbrella
 *     - inventory.manage              super-slug, matches cashflow.manage
 *   Brand
 *     - inventory.brand.view           datatable / index
 *     - inventory.brand.create
 *     - inventory.brand.edit
 *     - inventory.brand.delete
 *     - inventory.brand.toggle         activate / deactivate
 *   Product
 *     - inventory.product.view         datatable / index
 *     - inventory.product.create
 *     - inventory.product.edit
 *     - inventory.product.delete
 *     - inventory.product.toggle       activate / deactivate
 *     - inventory.product.sale_price   edit sale price
 *     - inventory.product.add_stock    receive inventory / batches
 *     - inventory.product.stock_detail view stock detail / batches / locations
 *     - inventory.product.transfer     transfer product between locations
 *     - inventory.product.log          audit trail / activity log
 *   Order
 *     - inventory.order.view           datatable / index
 *     - inventory.order.create
 *     - inventory.order.edit
 *     - inventory.order.delete
 *     - inventory.order.refund         create / approve order refund
 *
 * IMPORTANT — partial migration: legacy `brand_*`, `product_*`, `order_*`,
 * and `inventory_*` rows stay alive at status=1 because the
 * Admin\BrandsController / ProductsController / OrdersController + the
 * Blade index views + `Services\{Brand,Product,Order}Service` row-action
 * flags still gate on the underscore names. The companion
 * `hide_legacy_inventory_group` migration will flip them to status=0 once
 * the controllers/services/views switch to the dotted catalog. Until then
 * both names co-exist and the mirror map below keeps every role's
 * effective access intact on day 1.
 *
 * Mirror grants (legacy → new) used to seed grants on existing roles.
 * Anyone holding a legacy perm picks up the new dotted equivalent
 * automatically so the rewrite is non-breaking.
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
            ['name' => 'inventory.manage',                'title' => 'Manage everything (umbrella)'],

            ['name' => 'inventory.brand.view',            'title' => 'Brand · View'],
            ['name' => 'inventory.brand.create',          'title' => 'Brand · Create'],
            ['name' => 'inventory.brand.edit',            'title' => 'Brand · Edit'],
            ['name' => 'inventory.brand.delete',          'title' => 'Brand · Delete'],
            ['name' => 'inventory.brand.toggle',          'title' => 'Brand · Activate / Deactivate'],

            ['name' => 'inventory.product.view',          'title' => 'Product · View'],
            ['name' => 'inventory.product.create',        'title' => 'Product · Create'],
            ['name' => 'inventory.product.edit',          'title' => 'Product · Edit'],
            ['name' => 'inventory.product.delete',        'title' => 'Product · Delete'],
            ['name' => 'inventory.product.toggle',        'title' => 'Product · Activate / Deactivate'],
            ['name' => 'inventory.product.sale_price',    'title' => 'Product · Edit sale price'],
            ['name' => 'inventory.product.add_stock',     'title' => 'Product · Add stock / receive batch'],
            ['name' => 'inventory.product.stock_detail',  'title' => 'Product · View stock detail / batches'],
            ['name' => 'inventory.product.transfer',      'title' => 'Product · Transfer between locations'],
            ['name' => 'inventory.product.log',           'title' => 'Product · View activity log'],

            ['name' => 'inventory.order.view',            'title' => 'Order · View'],
            ['name' => 'inventory.order.create',          'title' => 'Order · Create'],
            ['name' => 'inventory.order.edit',            'title' => 'Order · Edit'],
            ['name' => 'inventory.order.delete',          'title' => 'Order · Delete'],
            ['name' => 'inventory.order.refund',          'title' => 'Order · Refund'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function legacyToNewMap(): array
    {
        return [
            // Umbrella
            'inventory_manage'         => ['inventory.manage'],
            // Brand
            'brand_manage'             => ['inventory.brand.view'],
            'brand_create'             => ['inventory.brand.create'],
            'brand_edit'               => ['inventory.brand.edit'],
            'brand_destroy'            => ['inventory.brand.delete'],
            'brand_active'             => ['inventory.brand.toggle'],
            // Product
            'product_manage'           => ['inventory.product.view'],
            'product_create'           => ['inventory.product.create'],
            'product_edit'             => ['inventory.product.edit'],
            'product_destroy'          => ['inventory.product.delete'],
            'product_active'           => ['inventory.product.toggle'],
            'product_sale_price'       => ['inventory.product.sale_price'],
            'product_add_stock'        => ['inventory.product.add_stock'],
            'product_stock_detail'     => ['inventory.product.stock_detail'],
            'product_transfer_manage'  => ['inventory.product.transfer'],
            // `product_log` is checked in Admin\ProductsController but no
            // matching legacy row exists — leave it out of the mirror.
            // Order
            'order_manage'             => ['inventory.order.view'],
            'order_create'             => ['inventory.order.create'],
            // `order_edit` / `order_destroy` are checked in
            // Admin\OrdersController but no legacy rows exist either —
            // skipping their mirror keeps the migration honest.
            'order_refund_manage'      => ['inventory.order.refund'],
            'inventory_refund_manage'  => ['inventory.order.refund'],
        ];
    }

    public function up(): void
    {
        DB::transaction(function (): void {
            $group = Permission::updateOrCreate(
                ['name' => 'inventory'],
                [
                    'title' => 'Inventory',
                    'main_group' => 1,
                    'parent_id' => 0,
                    'status' => 1,
                    'category' => 'Inventory',
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

            // Admin roles: every new perm.
            foreach (Role::whereIn('name', self::ADMIN_ROLES)->get() as $role) {
                $role->givePermissionTo($newNames);
            }

            // Back-compat mirror: copy every legacy-perm grant onto the
            // matching new dotted perm so no role loses effective access
            // when the controller rewrite later flips to the new names.
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

            Permission::where('name', 'inventory')
                ->where('main_group', 1)
                ->where('category', 'Inventory')
                ->delete();

            app(PermissionRegistrar::class)->forgetCachedPermissions();
            foreach (self::ROLE_SERVICE_CACHE_KEYS as $key) {
                Cache::forget($key);
            }
        });
    }
};
