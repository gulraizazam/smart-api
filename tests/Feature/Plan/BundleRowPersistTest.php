<?php

declare(strict_types=1);

namespace Tests\Feature\Plan;

use App\Services\Plan\PlanService;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Adding a Package (source_type='bundle') to a plan in stage-draft (create)
 * mode must PERSIST the package_bundles row + one package_services child per
 * catalog service, with the sold price distributed across the children
 * (Bundles::calculatePrices contract — the children sum to the sold total,
 * no leakage into the last row). Pins PlanService::addBundleService's
 * stage_draft persistence.
 *
 * NOTE: the sibling service_bundle path (addServiceBundleToPlan) only
 * persists in EDIT mode — its create-mode stage-draft persistence is a
 * known gap (audit P1), tracked separately; not covered here.
 */
class BundleRowPersistTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    public function test_adding_a_package_in_stage_draft_persists_rows_and_distributes_price(): void
    {
        $this->seedFinancialFixtures();
        $this->actingAsAdmin(); // account 1 (org tax treatment lookup)

        $locId = (int) DB::table('locations')->insertGetId([
            'account_id' => 1, 'name' => 'Persist Centre', 'active' => 1,
            'city_id' => 1, 'region_id' => 1, 'tax_percentage' => 0, // 0% keeps the math clean
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // 3 catalog services, regular 10,000 each → regular total 30,000.
        $serviceIds = [];
        foreach ([0, 1, 2] as $i) {
            $serviceIds[] = (int) DB::table('services')->insertGetId([
                'name' => 'Svc '.$i.' '.uniqid(), 'price' => 10000, 'tax_treatment_type_id' => 1,
                'account_id' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // Package (bundles type=multiple): catalog regular total 30,000, SOLD 21,000.
        $bundleId = (int) DB::table('bundles')->insertGetId([
            'account_id' => 1, 'name' => '3x Svc Package', 'type' => 'multiple',
            'price' => 21000, 'services_price' => 30000, 'tax_treatment_type_id' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ($serviceIds as $sid) {
            DB::table('bundle_has_services')->insert([
                'bundle_id' => $bundleId, 'service_id' => $sid,
                'service_price' => 10000, 'calculated_price' => 7000,
            ]);
        }

        $randomId = 'PERSIST-'.uniqid();
        $result = app(PlanService::class)->addBundleService([
            'random_id' => $randomId,
            'bundle_id' => $bundleId,
            'source_type' => 'bundle',
            'location_id' => $locId,
            'net_amount' => 21000,   // the SOLD price
            'stage_draft' => true,   // create-mode draft persistence
            'sold_by' => null,
        ]);

        $this->assertTrue((bool) ($result['success'] ?? true), 'addBundleService should succeed.');

        // One package_bundles row, staged (package_id null), tagged 'bundle'.
        $rows = DB::table('package_bundles')->where('random_id', $randomId)->get();
        $this->assertCount(1, $rows, 'Exactly one package_bundles row must be persisted.');
        $this->assertSame('bundle', $rows[0]->source_type);
        $this->assertNull($rows[0]->package_id, 'Stage-draft row must have package_id = null (bound at final save).');
        $this->assertEqualsWithDelta(21000.0, (float) $rows[0]->net_amount, 0.5);

        // One child per catalog service, with the sold price distributed (sums to 21,000).
        $children = DB::table('package_services')->where('package_bundle_id', $rows[0]->id)->get();
        $this->assertCount(3, $children, 'One package_services child per catalog service must be persisted.');
        $childSum = (float) $children->sum('tax_including_price');
        $this->assertEqualsWithDelta(
            21000.0,
            $childSum,
            3.0, // allow a few rupees of ceil() rounding across 3 rows
            'Distributed child prices must sum to the bundle sold total (calculatePrices contract — no leakage).',
        );
    }
}
