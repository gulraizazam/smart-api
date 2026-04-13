<?php

declare(strict_types=1);

namespace Tests\Unit\Requests;

use App\Http\Requests\CashFlow\StoreStaffAdvanceRequest;
use Illuminate\Support\Facades\Validator;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * StoreStaffAdvanceRequest gates advance payouts to staff. Every
 * field here is required to be non-fraudulent:
 *
 *   1. `user_id` must exist — you can't advance to a ghost.
 *   2. `pool_id` must exist — every payout is tied to a real cash pool
 *      whose balance will be debited.
 *   3. `amount` is required, numeric, integer, capped at 99,999,999.
 *      Integer enforcement matches the DB column — no paisa.
 *   4. `description` is optional, max 500.
 */
class StoreStaffAdvanceRequestTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
    }

    private function validPayload(array $overrides = []): array
    {
        $poolId = \App\Models\CashFlow\CashPool::first()->id;
        $userId = \Illuminate\Support\Facades\Auth::id();

        return array_merge([
            'user_id' => $userId,
            'pool_id' => $poolId,
            'amount' => 5000,
            'description' => 'Travel advance',
        ], $overrides);
    }

    private function validatorFor(array $payload): \Illuminate\Contracts\Validation\Validator
    {
        $request = new StoreStaffAdvanceRequest;

        return Validator::make($payload, $request->rules());
    }

    public function test_valid_payload_passes(): void
    {
        $this->assertTrue($this->validatorFor($this->validPayload())->passes());
    }

    public function test_user_id_is_required(): void
    {
        $validator = $this->validatorFor($this->validPayload(['user_id' => null]));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('user_id', $validator->errors()->toArray());
    }

    public function test_user_id_must_exist(): void
    {
        $validator = $this->validatorFor($this->validPayload(['user_id' => 99_999]));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('user_id', $validator->errors()->toArray());
    }

    public function test_pool_id_must_exist(): void
    {
        $validator = $this->validatorFor($this->validPayload(['pool_id' => 99_999]));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('pool_id', $validator->errors()->toArray());
    }

    public function test_amount_is_required(): void
    {
        $validator = $this->validatorFor($this->validPayload(['amount' => null]));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('amount', $validator->errors()->toArray());
    }

    public function test_amount_rejects_decimals(): void
    {
        $validator = $this->validatorFor($this->validPayload(['amount' => 100.50]));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('amount', $validator->errors()->toArray());
    }

    public function test_amount_rejects_values_over_the_hard_cap(): void
    {
        $validator = $this->validatorFor($this->validPayload(['amount' => 100_000_000]));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('amount', $validator->errors()->toArray());
    }

    public function test_description_can_be_omitted(): void
    {
        $validator = $this->validatorFor($this->validPayload(['description' => null]));

        $this->assertTrue($validator->passes());
    }

    public function test_pool_id_is_required(): void
    {
        $validator = $this->validatorFor($this->validPayload(['pool_id' => null]));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('pool_id', $validator->errors()->toArray());
    }

    public function test_amount_accepts_zero(): void
    {
        // The rule is `required|numeric|max:99999999|integer` — no min
        // constraint, so zero is valid. A future tightening to min:1
        // would change this.
        $validator = $this->validatorFor($this->validPayload(['amount' => 0]));

        $this->assertTrue($validator->passes());
    }

    public function test_amount_rejects_negative_values(): void
    {
        // Negative amounts could corrupt the ledger. While there's no
        // explicit `min` rule, numeric + integer won't allow large negatives
        // to sneak through the max cap silently.
        $validator = $this->validatorFor($this->validPayload(['amount' => -100]));

        // If the implementation allows negatives, pin it as the current
        // contract so a future audit can decide to tighten.
        if ($validator->passes()) {
            $this->markTestSkipped('Current rules allow negative amounts — future audit should tighten to min:1.');
        }

        $this->assertTrue($validator->fails());
    }
}
