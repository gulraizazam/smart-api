<?php

declare(strict_types=1);

namespace Tests\Feature\Plan;

use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * The 2026_06_10 backfill migration tags only the BLANK plan lines, using
 * the proven 2026-02-27 heuristic, and never overwrites an existing label
 * or touches memberships.
 */
class BackfillSourceTypeMigrationTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
    }

    private function runBackfill(): void
    {
        $migration = require database_path(
            'migrations/2026_06_10_120000_backfill_source_type_for_untagged_package_bundles.php',
        );
        $migration->up();
    }

    private function insertBundle(array $over): int
    {
        return (int) DB::table('package_bundles')->insertGetId(array_merge([
            'random_id' => 'MIG-'.uniqid(),
            'is_allocate' => 1,
            'qty' => 1,
            'discount_price' => 0,
            'service_price' => 1000,
            'net_amount' => 1000,
            'tax_exclusive_net_amount' => 1000,
            'tax_percentage' => 0,
            'tax_price' => 0,
            'tax_including_price' => 1000,
            'location_id' => 1,
            'bundle_id' => null,
            'source_type' => null,
            'membership_type_id' => null,
            'account_id' => 1,
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ], $over));
    }

    private function insertChild(int $packageBundleId, int $serviceId): void
    {
        DB::table('package_services')->insert([
            'package_bundle_id' => $packageBundleId,
            'service_id' => $serviceId,
            'orignal_price' => 1000,
            'price' => 1000,
            'tax_including_price' => 1000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function srcType(int $id): ?string
    {
        return DB::table('package_bundles')->where('id', $id)->value('source_type');
    }

    public function test_backfill_labels_blanks_via_heuristic_and_leaves_the_rest_alone(): void
    {
        // A catalog bundle to exercise the "bundle_id exists in bundles" branch.
        $bundleId = (int) DB::table('bundles')->insertGetId([
            'name' => 'Heuristic Bundle',
            'price' => 5000,
            'services_price' => 5000,
            'total_services' => 2,
            'type' => 'multiple',
            'active' => 1,
            'tax_treatment_type_id' => 1,
            'account_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Real services (the test DB enforces package_services.service_id FK).
        $svc = fn (string $name) => (int) DB::table('services')->insertGetId([
            'name' => $name, 'price' => 1000, 'end_node' => 1,
            'tax_treatment_type_id' => 1, 'account_id' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $svc1 = $svc('Mig Service 1');
        $svc2 = $svc('Mig Service 2');
        $svc3 = $svc('Mig Service 3');

        // 1) Single service: bundle_id is the service's own id, 1 child whose
        //    service_id == bundle_id → 'service'.
        $serviceRow = $this->insertBundle(['bundle_id' => $svc1]);
        $this->insertChild($serviceRow, $svc1);

        // 2) Bundle: >1 child → 'bundle'.
        $bundleRow = $this->insertBundle(['bundle_id' => $bundleId]);
        $this->insertChild($bundleRow, $svc2);
        $this->insertChild($bundleRow, $svc3);

        // 3) No children, bundle_id exists in bundles → 'bundle'.
        $noChildBundleRow = $this->insertBundle(['bundle_id' => $bundleId]);

        // 4) Membership (membership_type_id set) → excluded, stays NULL.
        $memTypeId = (int) DB::table('membership_types')->insertGetId([
            'name' => 'Mig Membership',
            'period' => 12,
            'created_by' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $membershipRow = $this->insertBundle(['membership_type_id' => $memTypeId, 'bundle_id' => null]);

        // 5) Already labeled 'service' but shaped like a bundle (>1 child) →
        //    must NOT be overwritten (only fills blanks).
        $preLabeledRow = $this->insertBundle(['bundle_id' => $bundleId, 'source_type' => 'service']);
        $this->insertChild($preLabeledRow, $svc2);
        $this->insertChild($preLabeledRow, $svc3);

        $this->runBackfill();

        $this->assertSame('service', $this->srcType($serviceRow), 'single-service line → service');
        $this->assertSame('bundle', $this->srcType($bundleRow), 'multi-child line → bundle');
        $this->assertSame('bundle', $this->srcType($noChildBundleRow), 'childless line whose bundle_id is a real bundle → bundle');
        $this->assertNull($this->srcType($membershipRow), 'membership line stays NULL (matches crm2)');
        $this->assertSame('service', $this->srcType($preLabeledRow), 'an already-labeled line is never overwritten');
    }
}
