<?php

declare(strict_types=1);

namespace Tests\Feature\Plan;

use App\Models\Packages;
use App\Models\PaymentModes;
use App\Services\Refund\RefundService;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Refund reservation guard (Shahid 2026-06-10).
 *
 * When a free/discounted GET is consumed ahead of its paid BUY (only possible
 * on a fully-paid plan), the BUY's money is RESERVED. A refund must not return
 * that reserved money — otherwise a client keeps a free GET while the paying
 * BUY is stranded and refunded. Only genuine surplus stays refundable. The
 * rule lives in ConsumptionReservation; RefundService enforces it on both the
 * display calc and the server-side create.
 */
class PlanRefundReservationTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private RefundService $refunds;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
        $this->refunds = app(RefundService::class);
    }

    public function test_create_refund_is_blocked_when_money_is_reserved(): void
    {
        // Fully-paid Buy/Get; free GET used out of order, paid BUY unconsumed.
        $plan = $this->makePlan();
        $this->configSession($plan, 7001, order: 1, sold: 10000, consumed: 0); // BUY reserved
        $this->configSession($plan, 7001, order: 3, sold: 0, consumed: 1);     // free GET used
        $this->pay($plan, 10000);

        $result = $this->refunds->createRefund(
            ['package_id' => $plan->id, 'refund_amount' => 10000],
            accountId: 1,
        );

        $this->assertFalse($result['success'],
            'Refunding the money reserved for the skipped BUY must be refused.');
        $this->assertStringContainsString('reserved', $result['message']);

        $this->assertSame(0, DB::table('package_advances')
            ->where('package_id', $plan->id)->where('cash_flow', 'out')->count(),
            'No refund advance may be written for a blocked refund.');
    }

    public function test_calculate_refund_deducts_exactly_the_reserved_amount(): void
    {
        // Two identical overpaid plans; one has the free GET consumed out of
        // order (reserves the 10000 BUY), the other does not. The refundable
        // gap must equal the reserved amount — independent of doc charges.
        $reservedPlan = $this->makePlan();
        $this->configSession($reservedPlan, 7002, order: 1, sold: 10000, consumed: 0); // BUY
        $this->configSession($reservedPlan, 7002, order: 3, sold: 0, consumed: 1);     // free GET used → reserves BUY
        $this->pay($reservedPlan, 12000);

        $cleanPlan = $this->makePlan();
        $this->configSession($cleanPlan, 7003, order: 1, sold: 10000, consumed: 0); // BUY
        $this->configSession($cleanPlan, 7003, order: 3, sold: 0, consumed: 0);     // free GET untouched → nothing reserved
        $this->pay($cleanPlan, 12000);

        $reservedRefundable = (int) $this->refunds->calculateRefund($reservedPlan->id)['refundable_amount'];
        $cleanRefundable = (int) $this->refunds->calculateRefund($cleanPlan->id)['refundable_amount'];

        $this->assertSame(10000, $cleanRefundable - $reservedRefundable,
            'The reservation must reduce the refundable by exactly the reserved BUY (10000).');
        $this->assertGreaterThan(0, $reservedRefundable,
            'Genuine surplus over the reserved amount stays refundable.');
    }

    /* ===================== helpers ===================== */

    private function makePlan(): Packages
    {
        return Packages::factory()->create([
            'account_id' => 1,
            'location_id' => $this->defaultLocation->id,
            'plan_type' => 'plan',
            'total_price' => 10000,
            'random_id' => 'REF-'.uniqid(),
        ]);
    }

    private function configSession(Packages $plan, int $configGroupId, int $order, float $sold, int $consumed): int
    {
        $bundleRowId = (int) DB::table('package_bundles')->insertGetId([
            'account_id' => 1,
            'package_id' => $plan->id,
            'bundle_id' => 1,
            'qty' => 1,
            'source_type' => 'service',
            'config_group_id' => $configGroupId,
            'service_price' => $sold,
            'net_amount' => $sold,
            'tax_including_price' => $sold,
            'tax_percentage' => 0,
            'random_id' => $plan->random_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $serviceId = (int) DB::table('services')->insertGetId([
            'name' => 'Svc '.uniqid(),
            'price' => $sold,
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
            'orignal_price' => $sold,
            'tax_including_price' => $sold,
            'tax_exclusive_price' => $sold,
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
}
