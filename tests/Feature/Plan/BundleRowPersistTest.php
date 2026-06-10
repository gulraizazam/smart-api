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

    /**
     * Adding a service_bundle (source_type='service_bundle' — the /bundles
     * product: N sessions of ONE service) to a NEW plan in stage-draft mode
     * must now PERSIST the package_bundles row + one package_services child
     * per session, with the SERVICE_BUNDLE name ("3x <service>") and the
     * persisted row id returned. Pins the create-mode persistence that was a
     * known gap (addServiceBundleToPlan previously only persisted on edit, so
     * a create-mode service_bundle was lost on save and 500'd on delete), and
     * proves the SPA→backend source_type now resolves the RIGHT catalog (no
     * bundles-table id-collision / wrong name).
     */
    public function test_adding_a_service_bundle_in_stage_draft_persists_rows(): void
    {
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();

        $locId = (int) DB::table('locations')->insertGetId([
            'account_id' => 1, 'name' => 'SB Centre', 'active' => 1,
            'city_id' => 1, 'region_id' => 1, 'tax_percentage' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $serviceId = (int) DB::table('services')->insertGetId([
            'name' => 'Coolglide Face Test', 'price' => 10000, 'tax_treatment_type_id' => 1,
            'account_id' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        // service_bundle: 3 sessions, sold 21,000.
        $sbId = (int) DB::table('service_bundles')->insertGetId([
            'service_id' => $serviceId, 'sessions' => 3, 'price' => 21000,
            'account_id' => 1, 'active' => 1, 'sort_number' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $randomId = 'SB-PERSIST-'.uniqid();
        $result = app(PlanService::class)->addBundleService([
            'random_id' => $randomId,
            'bundle_id' => $sbId,
            'source_type' => 'service_bundle', // routes to addServiceBundleToPlan
            'location_id' => $locId,
            'net_amount' => 21000,
            'stage_draft' => true,
            'sold_by' => null,
        ]);

        // Right catalog → service_bundle name, not a colliding bundles name.
        $this->assertStringContainsString('3x', $result['servicesData']['service_name']);
        $this->assertStringContainsString('Coolglide Face Test', $result['servicesData']['service_name']);
        $this->assertSame('service_bundle', $result['servicesData']['bundlesData']['source_type']);

        // One staged package_bundles row (package_id null), tagged service_bundle.
        $rows = DB::table('package_bundles')->where('random_id', $randomId)->get();
        $this->assertCount(1, $rows, 'Create-mode service_bundle must persist exactly one row.');
        $this->assertSame('service_bundle', $rows[0]->source_type);
        $this->assertNull($rows[0]->package_id, 'Stage-draft row must have package_id = null.');

        // One package_services child per session (3), summing to the sold total.
        $children = DB::table('package_services')->where('package_bundle_id', $rows[0]->id)->get();
        $this->assertCount(3, $children, 'One child per session must be persisted.');
        $this->assertEqualsWithDelta(21000.0, (float) $children->sum('tax_including_price'), 3.0);

        // Returned id is the PERSISTED row id, not the catalog id — so the
        // staged row is real (deletable + savable).
        $this->assertSame((int) $rows[0]->id, (int) $result['servicesData']['bundlesData']['id']);
    }

    /**
     * Deleting a STAGED draft row (package_id = NULL) must succeed for the
     * owning create session and be refused for any other. Regression: the
     * account-ownership guard called findOwnedPackage($pkg->package_id) with
     * a NULL package_id on draft rows → TypeError (500), which broke every
     * create-mode row delete. The fix scopes drafts by random_id instead.
     */
    public function test_deleting_a_staged_draft_row_is_session_scoped_not_a_500(): void
    {
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();

        $locId = (int) DB::table('locations')->insertGetId([
            'account_id' => 1, 'name' => 'Del Centre', 'active' => 1,
            'city_id' => 1, 'region_id' => 1, 'tax_percentage' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $svcId = (int) DB::table('services')->insertGetId([
            'name' => 'Svc Del', 'price' => 10000, 'tax_treatment_type_id' => 1,
            'account_id' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $bundleId = (int) DB::table('bundles')->insertGetId([
            'account_id' => 1, 'name' => '1x Svc Package', 'type' => 'multiple',
            'price' => 7000, 'services_price' => 10000, 'tax_treatment_type_id' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('bundle_has_services')->insert([
            'bundle_id' => $bundleId, 'service_id' => $svcId,
            'service_price' => 10000, 'calculated_price' => 7000,
        ]);

        $randomId = 'DEL-STAGED-'.uniqid();
        $result = app(PlanService::class)->addBundleService([
            'random_id' => $randomId, 'bundle_id' => $bundleId, 'source_type' => 'bundle',
            'location_id' => $locId, 'net_amount' => 7000, 'stage_draft' => true, 'sold_by' => null,
        ]);
        $rowId = (int) $result['servicesData']['bundlesData']['id'];
        $this->assertNull(DB::table('package_bundles')->where('id', $rowId)->value('package_id'));

        // A DIFFERENT session must not be able to delete this draft.
        $wrong = app(PlanService::class)->deletePackageService([
            'id' => $rowId, 'random_id' => 'SOMEONE-ELSE', 'update_status' => 1,
        ]);
        $this->assertFalse((bool) ($wrong['success'] ?? false), 'Foreign session must be refused.');
        $this->assertTrue(DB::table('package_bundles')->where('id', $rowId)->exists());

        // The owning session deletes it — no NULL-package_id 500.
        $ok = app(PlanService::class)->deletePackageService([
            'id' => $rowId, 'random_id' => $randomId, 'update_status' => 1,
        ]);
        $this->assertTrue((bool) ($ok['success'] ?? false), $ok['message'] ?? 'delete failed');
        $this->assertFalse(DB::table('package_bundles')->where('id', $rowId)->exists());
    }
}
