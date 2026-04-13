<?php

declare(strict_types=1);

namespace Tests\Unit\Requests;

use App\Http\Requests\CashFlow\StoreVendorTransactionRequest;
use Illuminate\Support\Facades\Validator;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * StoreVendorTransactionRequest gates the vendor-ledger write path.
 * A malformed row here would mis-price the vendor payable balance
 * on the dashboard.
 *
 * Invariants:
 *   1. `vendor_id` must exist in `cashflow_vendors`.
 *   2. `type` is strictly `purchase` or `payment` — the ledger
 *      aggregator sums with a sign flip by type; a third value
 *      would corrupt the balance calculation.
 *   3. `amount` is required, numeric, positive (min 0.01), capped
 *      at 99,999,999.99. Unlike expenses, vendor transactions are
 *      stored in a DECIMAL column so fractional amounts are legal.
 */
class StoreVendorTransactionRequestTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private int $vendorId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();

        $this->vendorId = \Illuminate\Support\Facades\DB::table('cashflow_vendors')->insertGetId([
            'account_id' => \Illuminate\Support\Facades\Auth::user()->account_id,
            'name' => 'Acme Supplies',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'vendor_id' => $this->vendorId,
            'type' => 'purchase',
            'amount' => 1500.75,
            'description' => 'Gauze pads bulk order',
        ], $overrides);
    }

    private function validatorFor(array $payload): \Illuminate\Contracts\Validation\Validator
    {
        return Validator::make($payload, (new StoreVendorTransactionRequest)->rules());
    }

    public function test_valid_payload_passes(): void
    {
        $validator = $this->validatorFor($this->validPayload());
        $this->assertTrue(
            $validator->passes(),
            'Baseline payload should validate — errors: ' . $validator->errors()->first(),
        );
    }

    public function test_vendor_id_must_exist(): void
    {
        $validator = $this->validatorFor($this->validPayload(['vendor_id' => 99_999]));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('vendor_id', $validator->errors()->toArray());
    }

    public function test_type_must_be_purchase_or_payment(): void
    {
        $validator = $this->validatorFor($this->validPayload(['type' => 'refund']));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('type', $validator->errors()->toArray());
    }

    public function test_type_payment_is_a_legal_value(): void
    {
        $validator = $this->validatorFor($this->validPayload(['type' => 'payment']));
        $this->assertTrue($validator->passes());
    }

    public function test_amount_must_be_positive(): void
    {
        $validator = $this->validatorFor($this->validPayload(['amount' => 0]));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('amount', $validator->errors()->toArray());
    }

    public function test_amount_allows_fractional_values(): void
    {
        // Unlike expenses, vendor transactions use a DECIMAL column;
        // paisa-level precision is legal.
        $validator = $this->validatorFor($this->validPayload(['amount' => 1500.55]));
        $this->assertTrue($validator->passes());
    }

    public function test_amount_rejects_values_over_the_hard_cap(): void
    {
        $validator = $this->validatorFor($this->validPayload(['amount' => 100_000_000]));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('amount', $validator->errors()->toArray());
    }

    public function test_amount_at_minimum_threshold_passes(): void
    {
        // DECIMAL column — min:0.01 rule. Exactly 0.01 must pass.
        $validator = $this->validatorFor($this->validPayload(['amount' => 0.01]));
        $this->assertTrue(
            $validator->passes(),
            'Amount of 0.01 should pass — errors: ' . $validator->errors()->first(),
        );
    }

    public function test_vendor_id_is_required(): void
    {
        $validator = $this->validatorFor($this->validPayload(['vendor_id' => null]));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('vendor_id', $validator->errors()->toArray());
    }
}
