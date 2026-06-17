<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Bundles;
use App\Models\MembershipType;
use App\Models\PackageBundles;
use App\Models\ServiceBundle;
use App\Models\Services;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\TestCase;

/**
 * Pins step 1 of the package_bundles.bundle_id de-overload: the table gained
 * three typed, single-purpose catalog FK columns that mechanically mirror the
 * overloaded (bundle_id, source_type) pair —
 *   catalog_service_id        -> services        (source_type 'service')
 *   catalog_bundle_id         -> bundles         (source_type 'bundle' = UI "Package")
 *   catalog_service_bundle_id -> service_bundles (source_type 'service_bundle' = UI "Bundle")
 *
 * Goes red if the add-typed-columns migration is reverted/removed. Also asserts
 * the legacy bundle_id + source_type are PRESERVED — the change is additive, they
 * remain the fallback until the readers are repointed and the before/after
 * name+price reconcile is proven zero-diff. See plan Tier T5 / glossary in
 * project_packages_bundles_naming + project_plans_module_overloaded_bundle_id.
 */
class PackageBundlesTypedCatalogColumnsTest extends TestCase
{
    use RefreshDatabase;

    public function test_typed_catalog_columns_exist(): void
    {
        foreach (['catalog_service_id', 'catalog_bundle_id', 'catalog_service_bundle_id'] as $col) {
            $this->assertTrue(
                Schema::hasColumn('package_bundles', $col),
                "package_bundles.{$col} should be added by the bundle_id de-overload migration.",
            );
        }
    }

    public function test_legacy_overloaded_columns_are_preserved(): void
    {
        // Additive-only: the old overloaded column + its discriminator stay as
        // the fallback. If a future change drops either, this goes red on purpose.
        $this->assertTrue(
            Schema::hasColumn('package_bundles', 'bundle_id'),
            'Legacy package_bundles.bundle_id must be preserved as the fallback during the de-overload.',
        );
        $this->assertTrue(
            Schema::hasColumn('package_bundles', 'source_type'),
            'Legacy package_bundles.source_type must be preserved as the fallback during the de-overload.',
        );
    }

    /**
     * Create a package_bundles row, supplying the NOT-NULL columns the row
     * shape requires (qty / service_price / net_amount) so each test only has
     * to express the catalog-routing attributes it actually cares about.
     */
    private function makeRow(array $attrs): PackageBundles
    {
        return PackageBundles::create(array_merge([
            'qty' => 1, 'service_price' => 0, 'net_amount' => 0, 'account_id' => 1,
        ], $attrs));
    }

    public function test_create_mirrors_each_source_type_into_its_typed_column(): void
    {
        // Same bundle_id value across all three flavours — the exact id-collision
        // the de-overload fixes. The mirror must route each to the RIGHT column.
        $service = $this->makeRow([
            'random_id' => 'TYPEDTEST-SVC', 'bundle_id' => 4242, 'source_type' => 'service',
        ])->fresh();
        $package = $this->makeRow([
            'random_id' => 'TYPEDTEST-PKG', 'bundle_id' => 4242, 'source_type' => 'bundle',
        ])->fresh();
        $serviceBundle = $this->makeRow([
            'random_id' => 'TYPEDTEST-SB', 'bundle_id' => 4242, 'source_type' => 'service_bundle',
        ])->fresh();

        $this->assertSame(4242, (int) $service->catalog_service_id);
        $this->assertNull($service->catalog_bundle_id);
        $this->assertNull($service->catalog_service_bundle_id);

        $this->assertSame(4242, (int) $package->catalog_bundle_id);
        $this->assertNull($package->catalog_service_id);
        $this->assertNull($package->catalog_service_bundle_id);

        $this->assertSame(4242, (int) $serviceBundle->catalog_service_bundle_id);
        $this->assertNull($serviceBundle->catalog_service_id);
        $this->assertNull($serviceBundle->catalog_bundle_id);
    }

    public function test_membership_line_keeps_all_typed_columns_null(): void
    {
        // Membership lines are exempt from the type-tag guard (source_type NULL)
        // and key off membership_type_id — no typed catalog column applies.
        $row = $this->makeRow([
            'random_id' => 'TYPEDTEST-MEM', 'membership_type_id' => 7,
        ])->fresh();

        $this->assertNull($row->catalog_service_id);
        $this->assertNull($row->catalog_bundle_id);
        $this->assertNull($row->catalog_service_bundle_id);
    }

    public function test_retagging_source_type_clears_the_stale_typed_column(): void
    {
        $row = $this->makeRow([
            'random_id' => 'TYPEDTEST-RETAG', 'bundle_id' => 99, 'source_type' => 'bundle',
        ]);
        $this->assertSame(99, (int) $row->fresh()->catalog_bundle_id);

        // Re-tag as a service_bundle row — catalog_bundle_id must clear and
        // catalog_service_bundle_id must take over (no stale column left behind).
        $row->update(['source_type' => 'service_bundle']);
        $fresh = $row->fresh();

        $this->assertNull($fresh->catalog_bundle_id);
        $this->assertSame(99, (int) $fresh->catalog_service_bundle_id);
    }

    public function test_canonical_resolver_routes_each_type_despite_id_collision(): void
    {
        // THE de-overload proof: one id (777) deliberately reused across the
        // services / bundles / service_bundles / membership_types catalogs —
        // the exact collision that mis-resolves names today. The resolver must
        // route each plan row to its OWN catalog record, never the colliding one.
        $cid = 777;
        DB::table('services')->insert(['id' => $cid, 'name' => 'COLLIDE Botox', 'tax_treatment_type_id' => 1, 'account_id' => 1]);
        DB::table('services')->insert(['id' => 888, 'name' => 'Laser Underlying', 'tax_treatment_type_id' => 1, 'account_id' => 1]);
        DB::table('bundles')->insert(['id' => $cid, 'name' => 'COLLIDE Gold Package', 'tax_treatment_type_id' => 1, 'account_id' => 1]);
        DB::table('service_bundles')->insert(['id' => $cid, 'service_id' => 888, 'sessions' => 3, 'price' => 100, 'account_id' => 1]);
        DB::table('membership_types')->insert(['id' => $cid, 'name' => 'COLLIDE VIP', 'period' => 12, 'created_by' => 1]);

        $service = $this->makeRow(['random_id' => 'RES-SVC', 'bundle_id' => $cid, 'source_type' => 'service']);
        $package = $this->makeRow(['random_id' => 'RES-PKG', 'bundle_id' => $cid, 'source_type' => 'bundle']);
        $serviceBundle = $this->makeRow(['random_id' => 'RES-SB', 'bundle_id' => $cid, 'source_type' => 'service_bundle', 'qty' => 3]);
        $membership = $this->makeRow(['random_id' => 'RES-MEM', 'membership_type_id' => $cid]);

        $this->assertSame('service', $service->catalogType());
        $this->assertSame('COLLIDE Botox', $service->catalogDisplayName());
        $this->assertInstanceOf(Services::class, $service->catalogItem());

        $this->assertSame('bundle', $package->catalogType());
        $this->assertSame('COLLIDE Gold Package', $package->catalogDisplayName());
        $this->assertInstanceOf(Bundles::class, $package->catalogItem());

        // service_bundle shows the UNDERLYING service prefixed by qty.
        $this->assertSame('service_bundle', $serviceBundle->catalogType());
        $this->assertSame('3x Laser Underlying', $serviceBundle->catalogDisplayName());
        $this->assertInstanceOf(ServiceBundle::class, $serviceBundle->catalogItem());

        $this->assertSame('membership', $membership->catalogType());
        $this->assertSame('COLLIDE VIP', $membership->catalogDisplayName());
        $this->assertInstanceOf(MembershipType::class, $membership->catalogItem());
    }

    public function test_typed_catalog_columns_carry_fk_constraints(): void
    {
        // The three typed columns are FK-constrained to their catalogs. We assert
        // the constraint DEFINITION (information_schema), not runtime rejection:
        // the mariadb_testing connection runs `SET FOREIGN_KEY_CHECKS=0`
        // (config/database.php) so tests can use partial fixtures, so an orphan
        // insert can't be rejected here. Runtime enforcement is a PROD concern
        // (real InnoDB, checks on) — proven by applying this FK migration to the
        // 146K-row prod-shaped mirror (0 orphans, applied clean). This pins that
        // the constraint ships in the schema.
        $names = array_map(
            static fn ($r): string => $r->CONSTRAINT_NAME,
            DB::select(
                'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE '
                .'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME LIKE ?',
                ['package_bundles', 'fk_pb_catalog_%'],
            ),
        );

        foreach (['fk_pb_catalog_service', 'fk_pb_catalog_bundle', 'fk_pb_catalog_service_bundle'] as $fk) {
            $this->assertContains($fk, $names, "Foreign key {$fk} must constrain its typed catalog column.");
        }
    }
}
