<?php

declare(strict_types=1);

namespace Tests\Feature\Plan;

use App\Models\Packages;
use App\Services\Plan\PlanService;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Pins the "Edit → Update is SAFE" invariant from the 2026-06-09 audit:
 * opening an existing plan and clicking Update (the SPA sends row IDs only —
 * the "simple ID format") must NOT rewrite the stored per-row prices. The
 * backend's storePackageBundlesOptimized simple-ID branch does only a
 * package_id/is_allocate bind via whereIn()->update(); it must never touch
 * service_price / net_amount / discount_price / tax_including_price.
 *
 * This was audit-verified but had NO test — so a refactor that made the
 * update path recompute prices would silently corrupt every edited plan's
 * money. This test goes red if that happens.
 */
class PlanEditUpdatePricePreservationTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    public function test_update_with_row_ids_only_preserves_stored_row_prices(): void
    {
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
        $service = app(PlanService::class);

        $patientId = (int) DB::table('users')->insertGetId([
            'account_id' => 1, 'name' => 'Edit Patient', 'email' => 'ep+'.uniqid().'@x.test',
            'password' => bcrypt('x'), 'user_type_id' => 3, 'active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $appointmentId = (int) DB::table('appointments')->insertGetId([
            'account_id' => 1, 'patient_id' => $patientId, 'location_id' => $this->defaultLocation->id,
            'lead_id' => 0, 'doctor_id' => 0, 'region_id' => 1, 'city_id' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $randomId = 'EDIT-RID-'.uniqid();
        $plan = Packages::factory()->create([
            'account_id' => 1, 'location_id' => $this->defaultLocation->id,
            'random_id' => $randomId, 'plan_type' => 'plan', 'appointment_id' => $appointmentId,
            'total_price' => 8995, 'patient_id' => $patientId,
        ]);

        // A discounted line with DISTINCT stored prices — if any get
        // recomputed/overwritten on update, the assertions below catch it.
        $bundleId = (int) DB::table('package_bundles')->insertGetId([
            'account_id' => 1, 'package_id' => $plan->id, 'bundle_id' => 1, 'qty' => 1,
            'source_type' => 'service', 'random_id' => $randomId, 'is_allocate' => 1,
            'service_price' => 13995, 'net_amount' => 13995, 'discount_price' => 5000,
            'tax_including_price' => 8995, 'tax_exclusive_net_amount' => 8995,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('package_services')->insert([
            'package_id' => $plan->id, 'package_bundle_id' => $bundleId, 'random_id' => $randomId,
            'price' => 8995, 'orignal_price' => 13995, 'is_consumed' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $before = DB::table('package_bundles')->where('id', $bundleId)
            ->first(['service_price', 'net_amount', 'discount_price', 'tax_including_price', 'tax_exclusive_net_amount']);

        // The SPA's edit-save: row IDs only (the "simple ID format").
        $result = $service->updatePlanPackage([
            'random_id' => $randomId,
            'package_bundles' => [$bundleId],
            'total' => '8995',
            'appointment_id' => $appointmentId.'.A',
            'location_id' => $this->defaultLocation->id,
            'patient_id' => $patientId,
            'plan_type' => 'plan',
        ]);

        $this->assertTrue($result['status'], 'A clean edit-save must succeed.');

        $after = DB::table('package_bundles')->where('id', $bundleId)
            ->first(['service_price', 'net_amount', 'discount_price', 'tax_including_price', 'tax_exclusive_net_amount']);

        $this->assertEquals(
            (array) $before,
            (array) $after,
            'Edit→Update sent row IDs only — it must NOT rewrite any stored row price.',
        );
        // The bind step is allowed to (re)affirm package_id + is_allocate.
        $this->assertSame($plan->id, (int) DB::table('package_bundles')->where('id', $bundleId)->value('package_id'));
        $this->assertSame(1, (int) DB::table('package_bundles')->where('id', $bundleId)->value('is_allocate'));
    }
}
