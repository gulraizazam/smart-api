<?php

declare(strict_types=1);

namespace Tests\Feature\Appointments;

use App\Models\Appointments;
use App\Models\Packages;
use App\Models\PaymentModes;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Regression: the treatment package-price endpoint must never return a
 * NEGATIVE settle_amount.
 *
 * Bug: invoicePackagePrice computed settle for the multi-service
 * ("multiple") bundle branch as `min(bundle_net_amount - balance, balance)`.
 * When the plan's available balance exceeds the bundle's remaining net
 * amount, that goes negative. The React consume modal passes that figure
 * straight into the save POST when no cash is typed, where the save
 * endpoint's `'settle' => 'min:0'` rule rejects it with
 * "The settle must be at least 0." — blocking a legitimate consumption.
 *
 * Fix: clamp settle_amount with `max(0, ...)` in the response, mirroring
 * how `outstanding` is already clamped on the same payload.
 */
class TreatmentPackagePriceSettleClampTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();

        // The endpoint only takes the multi-service settle branch when the
        // bundle row (package_bundles.bundle_id = 1 below) resolves to a
        // bundles row of type 'multiple'.
        DB::table('bundles')->updateOrInsert(
            ['id' => 1],
            [
                'name' => 'Multi Bundle',
                'price' => 10000,
                'type' => 'multiple',
                'tax_treatment_type_id' => 1,
                'account_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function test_package_price_never_returns_a_negative_settle(): void
    {
        // Multi-service bundle whose remaining net amount (1000) is LESS
        // than the plan balance (5000): the raw settle math is
        // min(1000 - 5000, 5000) = -4000. The bundle/service regular price
        // (10000) stays above the balance, so the per-service remainder
        // branch — the one that can go negative — is the one taken.
        $plan = Packages::factory()->create([
            'account_id' => 1,
            'location_id' => $this->defaultLocation->id,
            'plan_type' => 'plan',
            'total_price' => 1000,
            'random_id' => 'NEGSETTLE-'.uniqid(),
        ]);

        $sessionId = $this->configSession($plan, sold: 1000, regular: 10000);
        $this->pay($plan, 5000);

        $appointment = Appointments::factory()->create([
            'account_id' => 1,
            'appointment_type_id' => 2, // AppointmentType::Treatment
            'patient_id' => $plan->patient_id,
            'location_id' => $this->defaultLocation->id,
        ]);

        $response = $this->getJson(
            "/api/treatment/invoice/{$appointment->id}/package-price"
            ."?package_id={$plan->id}&package_service_id={$sessionId}"
        );

        $response->assertOk();

        $settle = $response->json('data.settle_amount');
        $this->assertNotNull($settle, 'package-price must return a settle_amount.');
        $this->assertGreaterThanOrEqual(
            0,
            $settle,
            'settle_amount must never be negative — the save endpoint rejects it (min:0).',
        );
    }

    /**
     * Insert one consumable session inside a `multiple` bundle:
     *   - package_bundles.net_amount = $sold   (what the plan paid toward it)
     *   - services.price / regular price       = $regular
     */
    private function configSession(Packages $plan, float $sold, float $regular): int
    {
        $bundleRowId = (int) DB::table('package_bundles')->insertGetId([
            'account_id' => 1,
            'package_id' => $plan->id,
            'bundle_id' => 1,
            'qty' => 1,
            'source_type' => 'service',
            'config_group_id' => null,
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
            'is_consumed' => 0,
            'consumption_order' => 1,
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
