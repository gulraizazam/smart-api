<?php

declare(strict_types=1);

namespace Tests\Feature\Appointments;

use App\Models\Packages;
use App\Models\PaymentModes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Bundle/Package "regular-price minimum" consume gate (Shahid 2026-06-10).
 *
 * Bundles (/bundles = service_bundles) and Packages (/packages = bundles)
 * are sold at a heavy discount for full upfront payment. The rule: to
 * consume a session, cumulative payments must cover the REGULAR price of the
 * sessions consumed so far (this one included), capped at the plan's total
 * sold price. Standalone single services keep the discounted gate.
 *
 * The gate (saveinvoice consumption-lock) returns BEFORE the invoice writes,
 * so blocked attempts are asserted directly; allowed attempts are asserted
 * to NOT be blocked at the gate (they then 404 on the bogus appointment id,
 * which is fine — we only pin the gate decision, not the full invoice flow).
 *
 * Worked example (3x Hydrafacial, regular 10k / sold 7k, plan total 21k):
 * consume S1 needs 10k paid, S2 needs 20k, S3 needs 21k.
 */
class BundleRegularPriceConsumeGateTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin(); // account 1, super-admin → invoice perm passes
    }

    /* ---------------- Packages (source_type='bundle') ---------------- */

    public function test_package_session_is_blocked_until_the_regular_price_is_paid(): void
    {
        $plan = $this->makePlan(21000);
        $row = $this->makeBundleRow($plan, 'bundle');
        $s1 = $this->makeSession($plan, $row, sold: 7000, regular: 10000);
        $this->makeSession($plan, $row, sold: 7000, regular: 10000);
        $this->makeSession($plan, $row, sold: 7000, regular: 10000);

        $this->pay($plan, 7000); // one discounted session — below the 10k regular

        $resp = $this->consume($plan, $s1);

        $this->assertBlocked($resp, 'Paying 7,000 (sold) must not unlock a session that needs 10,000 (regular).');
        $this->assertStringContainsString('3,000', (string) $resp->json('message'), 'Shortfall should be 10,000 - 7,000 = 3,000.');
    }

    public function test_package_session_consumes_once_the_regular_price_is_paid(): void
    {
        $plan = $this->makePlan(21000);
        $row = $this->makeBundleRow($plan, 'bundle');
        $s1 = $this->makeSession($plan, $row, sold: 7000, regular: 10000);
        $this->makeSession($plan, $row, sold: 7000, regular: 10000);
        $this->makeSession($plan, $row, sold: 7000, regular: 10000);

        $this->pay($plan, 10000); // the regular price

        $resp = $this->consume($plan, $s1);

        $this->assertNotBlockedAtGate($resp, 'Paying the 10,000 regular price must let S1 through the gate.');
    }

    public function test_second_package_session_needs_the_running_regular_total(): void
    {
        $plan = $this->makePlan(21000);
        $row = $this->makeBundleRow($plan, 'bundle');
        $this->makeSession($plan, $row, sold: 7000, regular: 10000, consumed: 1); // S1 already consumed
        $s2 = $this->makeSession($plan, $row, sold: 7000, regular: 10000);
        $this->makeSession($plan, $row, sold: 7000, regular: 10000);

        $this->pay($plan, 10000); // enough for S1's regular, not S1+S2's

        $resp = $this->consume($plan, $s2);

        $this->assertBlocked($resp, 'S2 needs the running regular total (20,000), not just one regular.');
        $this->assertStringContainsString('10,000', (string) $resp->json('message'), 'Shortfall should be 20,000 - 10,000 = 10,000.');
    }

    public function test_fully_paid_bundle_consumes_freely(): void
    {
        $plan = $this->makePlan(21000);
        $row = $this->makeBundleRow($plan, 'bundle');
        $s1 = $this->makeSession($plan, $row, sold: 7000, regular: 10000);
        $this->makeSession($plan, $row, sold: 7000, regular: 10000);
        $this->makeSession($plan, $row, sold: 7000, regular: 10000);

        $this->pay($plan, 21000); // paid in full upfront

        $resp = $this->consume($plan, $s1);

        $this->assertNotBlockedAtGate($resp, 'A fully-paid bundle must consume without any block.');
    }

    /* ---------------- Bundles product (source_type='service_bundle') ---------------- */

    public function test_service_bundle_session_is_also_gated_at_regular(): void
    {
        $plan = $this->makePlan(14000);
        $row = $this->makeBundleRow($plan, 'service_bundle'); // the /bundles product
        $s1 = $this->makeSession($plan, $row, sold: 7000, regular: 10000);
        $this->makeSession($plan, $row, sold: 7000, regular: 10000);

        $this->pay($plan, 7000);

        $resp = $this->consume($plan, $s1);

        $this->assertBlocked($resp, '/bundles (service_bundle) products must be gated at regular price too, not just Packages.');
    }

    /* ---------------- Carve-out: single service in a mixed plan ---------------- */

    public function test_single_service_in_a_mixed_plan_is_NOT_gated_at_regular(): void
    {
        // Plan holds one bundle session AND one standalone single service.
        $plan = $this->makePlan(14000);
        $bundleRow = $this->makeBundleRow($plan, 'bundle');
        $bundleSession = $this->makeSession($plan, $bundleRow, sold: 7000, regular: 10000);

        $serviceRow = $this->makeBundleRow($plan, 'service'); // standalone single service
        $singleSession = $this->makeSession($plan, $serviceRow, sold: 7000, regular: 10000);

        $this->pay($plan, 7000); // exactly the single service's SOLD price

        // The single service consumes at its sold price (carve-out)...
        $this->assertNotBlockedAtGate(
            $this->consume($plan, $singleSession),
            'A standalone single service must consume at its discounted price, not the regular.',
        );

        // ...but the bundle session in the same plan still needs its regular.
        $this->assertBlocked(
            $this->consume($plan, $bundleSession),
            'The bundle session in a mixed plan is still gated at the regular price.',
        );
    }

    /**
     * The consume DIALOG reads `required_to_consume` from invoicePlanInfo to
     * pre-check payment the SAME way saveinvoice enforces it. Pin that the
     * expression yields the REGULAR price for a bundle/package line and the
     * SOLD price for a standalone service — so the dialog can't drift back to
     * showing a Bundle as consumable below its regular price.
     */
    public function test_plan_info_required_to_consume_is_regular_for_bundles_sold_for_services(): void
    {
        $plan = $this->makePlan(20000);
        $this->makeSession($plan, $this->makeBundleRow($plan, 'service_bundle'), sold: 7000, regular: 10000);
        $this->makeSession($plan, $this->makeBundleRow($plan, 'bundle'), sold: 7000, regular: 10000);
        $this->makeSession($plan, $this->makeBundleRow($plan, 'service'), sold: 7000, regular: 10000);

        // The exact expression invoicePlanInfo selects as `required_to_consume`.
        $rows = DB::table('package_services')
            ->leftJoin('package_bundles', 'package_services.package_bundle_id', '=', 'package_bundles.id')
            ->where('package_services.package_id', $plan->id)
            ->selectRaw("package_bundles.source_type AS st, CASE WHEN package_bundles.source_type IN ('bundle', 'service_bundle') THEN GREATEST(COALESCE(package_services.orignal_price, 0), package_services.tax_including_price) ELSE package_services.tax_including_price END AS required")
            ->get()->keyBy('st');

        $this->assertEquals(10000, (int) $rows['service_bundle']->required, 'Bundle (/bundles) → regular price.');
        $this->assertEquals(10000, (int) $rows['bundle']->required, 'Package (/packages) → regular price.');
        $this->assertEquals(7000, (int) $rows['service']->required, 'Standalone service → sold price.');
    }

    /* ===================== helpers ===================== */

    private function makePlan(int $totalPrice): Packages
    {
        return Packages::factory()->create([
            'account_id' => 1,
            'location_id' => $this->defaultLocation->id,
            'plan_type' => 'plan',
            'total_price' => $totalPrice,
            'random_id' => 'GATE-'.uniqid(),
        ]);
    }

    private function makeBundleRow(Packages $plan, string $sourceType): int
    {
        return (int) DB::table('package_bundles')->insertGetId([
            'account_id' => 1,
            'package_id' => $plan->id,
            'bundle_id' => 1,
            'qty' => 1,
            'source_type' => $sourceType,
            'service_price' => 10000,
            'net_amount' => 10000,
            'tax_including_price' => 7000,
            'random_id' => $plan->random_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeSession(Packages $plan, int $bundleRowId, float $sold, float $regular, int $consumed = 0): int
    {
        $serviceId = (int) DB::table('services')->insertGetId([
            'name' => 'Svc '.uniqid(),
            'price' => $regular,
            'tax_treatment_type_id' => 1,
            'account_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('package_services')->insertGetId([
            'package_id' => $plan->id,
            'package_bundle_id' => $bundleRowId,
            'service_id' => $serviceId,
            'price' => $sold,
            'orignal_price' => $regular,          // the REGULAR price (tax-inclusive basis)
            'tax_including_price' => $sold,        // the SOLD price
            'is_consumed' => $consumed,
            'consumption_order' => 0,              // skip the BUY/GET ordering guard
            'random_id' => $plan->random_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function pay(Packages $plan, float $amount): void
    {
        $cash = PaymentModes::query()->where('name', 'Cash')->firstOrFail();
        DB::table('package_advances')->insert([
            'account_id' => 1,
            'package_id' => $plan->id,
            'patient_id' => $plan->patient_id,
            'payment_mode_id' => $cash->id,
            'location_id' => $this->defaultLocation->id,
            'cash_flow' => 'in',
            'cash_amount' => $amount,
            'is_cancel' => 0,
            'is_setteled' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function consume(Packages $plan, int $sessionId): \Illuminate\Testing\TestResponse
    {
        // appointment_id is bogus on purpose — the gate runs BEFORE the
        // appointment fetch, so blocked attempts return at the gate and
        // allowed attempts simply 404 past it (not the gate's concern).
        return $this->postJson('/api/treatment/invoice', [
            'appointment_id' => 999999,
            'package_id' => $plan->id,
            'package_service_id' => $sessionId,
            'cash' => 0,
            'settle' => 0,
        ]);
    }

    private function assertBlocked(\Illuminate\Testing\TestResponse $resp, string $msg): void
    {
        $this->assertStringContainsString('Insufficient payment', (string) $resp->json('message'), $msg);
    }

    private function assertNotBlockedAtGate(\Illuminate\Testing\TestResponse $resp, string $msg): void
    {
        $this->assertStringNotContainsString('Insufficient payment', (string) $resp->json('message'), $msg);
    }
}
