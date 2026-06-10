<?php

declare(strict_types=1);

namespace Tests\Feature\Appointments;

use App\Models\Packages;
use App\Models\PaymentModes;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Configurable Buy/Get consume flexibility + reservation (Shahid 2026-06-10).
 *
 * Rule: a NOT-fully-paid plan must consume paid (BUY) sessions before
 * discounted/free (GET) ones. Once the plan is FULLY PAID the client may
 * consume in any order — but a GET used ahead of its BUY RESERVES the BUY's
 * money, so a later top-up service can't be consumed on it (the reservation
 * is enforced by the consume gate via ConsumptionReservation::requiredFloor).
 *
 * The gate runs BEFORE the appointment fetch, so blocked attempts return at
 * the gate; allowed attempts pass it and 404 on the bogus appointment id —
 * we pin the gate decision only.
 */
class ConfigurableConsumeFlexibilityTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
    }

    public function test_fully_paid_plan_allows_a_free_get_before_its_buy(): void
    {
        // Buy 1 (10000) + free GET. Plan sold total = 10000.
        $plan = $this->makePlan(10000);
        $this->configSession($plan, 6001, order: 1, sold: 10000, regular: 10000); // BUY
        $freeGet = $this->configSession($plan, 6001, order: 3, sold: 0, regular: 10000); // free GET

        $this->pay($plan, 10000); // fully paid

        $this->assertNotBlockedAtGate(
            $this->consume($plan, $freeGet),
            'A fully-paid plan must let the free GET be consumed before the BUY (flexibility).',
        );
    }

    public function test_not_fully_paid_plan_blocks_a_free_get_before_its_buy(): void
    {
        $plan = $this->makePlan(10000);
        $this->configSession($plan, 6002, order: 1, sold: 10000, regular: 10000); // BUY
        $freeGet = $this->configSession($plan, 6002, order: 3, sold: 0, regular: 10000); // free GET

        $this->pay($plan, 5000); // NOT fully paid

        $resp = $this->consume($plan, $freeGet);

        $this->assertStringContainsString('consume the paid sessions first', (string) $resp->json('message'),
            'While money is still owed, the free GET cannot jump ahead of the paid BUY.');
    }

    public function test_added_service_cannot_spend_money_reserved_for_a_skipped_buy(): void
    {
        // Fully-paid Buy/Get; the free GET was already used out of order
        // (is_consumed=1), leaving the paid BUY unconsumed and reserved.
        $plan = $this->makePlan(10000);
        $this->configSession($plan, 6003, order: 1, sold: 10000, regular: 10000, consumed: 0); // BUY reserved
        $this->configSession($plan, 6003, order: 3, sold: 0, regular: 10000, consumed: 1);     // free GET used

        // Operator adds a standalone 6000 service to the plan.
        $newService = $this->configSession($plan, null, order: 0, sold: 6000, regular: 6000, consumed: 0);

        $this->pay($plan, 10000); // only the original money — nothing new for the add-on

        $resp = $this->consume($plan, $newService);

        $this->assertStringContainsString('Insufficient payment', (string) $resp->json('message'),
            'The new service must not be consumable on money reserved for the skipped BUY.');
        $this->assertStringContainsString('6,000', (string) $resp->json('message'),
            'Shortfall is the new service price (10000 reserved for the BUY leaves 0 toward the 6000 add-on).');
    }

    public function test_not_fully_paid_blocks_a_discounted_get_before_its_buy(): void
    {
        // The ordering rule applies to the priced GET (order 2), not just the free one.
        $plan = $this->makePlan(15000);
        $this->configSession($plan, 6101, order: 1, sold: 10000, regular: 10000); // BUY
        $disc = $this->configSession($plan, 6101, order: 2, sold: 5000, regular: 10000); // discounted GET
        $this->configSession($plan, 6101, order: 3, sold: 0, regular: 10000); // free GET

        $this->pay($plan, 5000); // NOT fully paid

        $resp = $this->consume($plan, $disc);

        $this->assertStringContainsString('consume the paid sessions first', (string) $resp->json('message'),
            'A discounted GET cannot jump ahead of the paid BUY while money is owed.');
    }

    public function test_fully_paid_allows_a_discounted_get_before_its_buy(): void
    {
        $plan = $this->makePlan(15000);
        $this->configSession($plan, 6102, order: 1, sold: 10000, regular: 10000); // BUY
        $disc = $this->configSession($plan, 6102, order: 2, sold: 5000, regular: 10000); // discounted GET
        $this->configSession($plan, 6102, order: 3, sold: 0, regular: 10000); // free GET

        $this->pay($plan, 15000); // fully paid

        $this->assertNotBlockedAtGate(
            $this->consume($plan, $disc),
            'A fully-paid plan may consume the discounted GET before the BUY.',
        );
    }

    public function test_the_reserved_buy_itself_stays_consumable(): void
    {
        // Same out-of-order setup as the add-service block, but consume the BUY
        // — it is exactly what the held money is for, so it must pass even while
        // the plan is not fully paid (the added service has diluted the total).
        $plan = $this->makePlan(16000);
        $buy = $this->configSession($plan, 6103, order: 1, sold: 10000, regular: 10000, consumed: 0); // BUY reserved
        $this->configSession($plan, 6103, order: 3, sold: 0, regular: 10000, consumed: 1);            // free GET used
        $this->configSession($plan, null, order: 0, sold: 6000, regular: 6000, consumed: 0);          // added service

        $this->pay($plan, 10000); // covers the BUY but not the add-on

        $this->assertNotBlockedAtGate(
            $this->consume($plan, $buy),
            'The reserved BUY is what the held money is for — it must stay consumable.',
        );
    }

    public function test_added_service_consumes_once_its_own_money_is_collected(): void
    {
        // The add-service block lifts when the new service's money is collected.
        $plan = $this->makePlan(16000);
        $this->configSession($plan, 6104, order: 1, sold: 10000, regular: 10000, consumed: 0); // BUY reserved
        $this->configSession($plan, 6104, order: 3, sold: 0, regular: 10000, consumed: 1);     // free GET used
        $newService = $this->configSession($plan, null, order: 0, sold: 6000, regular: 6000, consumed: 0);

        $this->pay($plan, 16000); // original 10000 + the 6000 add-on collected

        $this->assertNotBlockedAtGate(
            $this->consume($plan, $newService),
            'With the add-on money collected, the new service consumes.',
        );
    }

    /* ===================== helpers ===================== */

    private function makePlan(int $totalPrice): Packages
    {
        return Packages::factory()->create([
            'account_id' => 1,
            'location_id' => $this->defaultLocation->id,
            'plan_type' => 'plan',
            'total_price' => $totalPrice,
            'random_id' => 'FLEX-'.uniqid(),
        ]);
    }

    private function configSession(Packages $plan, ?int $configGroupId, int $order, float $sold, float $regular, int $consumed = 0, string $sourceType = 'service'): int
    {
        $bundleRowId = (int) DB::table('package_bundles')->insertGetId([
            'account_id' => 1,
            'package_id' => $plan->id,
            'bundle_id' => 1,
            'qty' => 1,
            'source_type' => $sourceType,
            'config_group_id' => $configGroupId,
            'service_price' => $regular,
            'net_amount' => $sold,
            'tax_including_price' => $sold,
            'random_id' => $plan->random_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

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
            'orignal_price' => $regular,
            'tax_including_price' => $sold,
            'is_consumed' => $consumed,
            'consumption_order' => $order,
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
        return $this->postJson('/api/treatment/invoice', [
            'appointment_id' => 999999,
            'package_id' => $plan->id,
            'package_service_id' => $sessionId,
            'cash' => 0,
            'settle' => 0,
        ]);
    }

    private function assertNotBlockedAtGate(\Illuminate\Testing\TestResponse $resp, string $msg): void
    {
        $message = (string) $resp->json('message');
        $this->assertStringNotContainsString('Insufficient payment', $message, $msg);
        $this->assertStringNotContainsString('consume the paid sessions first', $message, $msg);
    }
}
