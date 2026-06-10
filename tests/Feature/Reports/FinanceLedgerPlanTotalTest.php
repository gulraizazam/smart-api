<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Models\Packages;
use App\Reports\Finance\FinanceLedgerReport;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * The Finance Ledger reported each plan's total_price as SUM(net_amount),
 * which on discounted rows is the PRE-discount price — so discounted plans
 * were overstated (~342M across ~19.3k plans on the prod mirror). The fix
 * mirrors getDisplayData (the View dialog's confirmed-correct source):
 * SUM(package_services.price) for plans.
 *
 * This pins that the report total equals the real sold value, not the
 * pre-discount figure.
 */
class FinanceLedgerPlanTotalTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    public function test_report_plan_total_uses_real_sold_price_not_pre_discount_net_amount(): void
    {
        $this->seedFinancialFixtures();
        $user = $this->actingAsAdmin();
        // A freshly-factoried admin has no centres → ACL::getUserCentres()
        // returns []. Tie the user to the default centre so the report's
        // whereIn('location_id', getUserCentres()) includes our plan.
        DB::table('user_has_locations')->insert([
            'user_id' => $user->id,
            'location_id' => $this->defaultLocation->id,
            'region_id' => $this->defaultLocation->region_id,
        ]);

        $patientId = (int) DB::table('users')->insertGetId([
            'account_id' => 1, 'name' => 'Ledger Patient', 'email' => 'lp+'.uniqid().'@x.test',
            'password' => bcrypt('x'), 'user_type_id' => 3, 'active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Refunded plan (the report filters is_refund=1) with a discounted
        // row: net_amount=13,995 (pre-discount) but real sold price=8,995.
        $plan = Packages::factory()->create([
            'account_id' => 1, 'location_id' => $this->defaultLocation->id,
            'plan_type' => 'plan', 'is_refund' => 1, 'patient_id' => $patientId,
            'random_id' => 'LEDGER-RID-'.uniqid(),
        ]);
        $bundleId = (int) DB::table('package_bundles')->insertGetId([
            'account_id' => 1, 'package_id' => $plan->id, 'bundle_id' => 1, 'qty' => 1,
            'source_type' => 'service', 'service_price' => 13995, 'net_amount' => 13995,
            'discount_price' => 5000, 'tax_including_price' => 8995,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('package_services')->insert([
            'package_id' => $plan->id, 'package_bundle_id' => $bundleId, 'price' => 8995,
            'is_consumed' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $result = FinanceLedgerReport::ListofClientswhoclaimedrefundsdaywise(
            ['date_range' => '2026-01-01 - 2026-12-31', 'year' => 2026, 'month' => 6],
            1,
        );

        $this->assertArrayHasKey($plan->id, $result, 'The refunded plan must appear in the ledger.');
        $this->assertEqualsWithDelta(
            8995.0,
            (float) $result[$plan->id]['total_price'],
            0.5,
            'Report total must be the real sold price (services.price), NOT the pre-discount net_amount (13,995).',
        );
    }
}
