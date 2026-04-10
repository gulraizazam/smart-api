<?php

declare(strict_types=1);

namespace Tests\Unit\Requests;

use App\Http\Requests\CashFlow\StoreTransferRequest;
use Illuminate\Support\Facades\Validator;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * StoreTransferRequest gates cash transfers between pools — one of
 * the most accident-prone flows in the cash-flow module because a
 * typo here can silently strand money.
 *
 * Invariants:
 *
 *   1. Source pool and destination pool must BOTH exist, and they
 *      MUST be different (the `different:from_pool_id` rule). A
 *      self-transfer is a no-op that would still update timestamps
 *      and confuse the audit log.
 *   2. `transfer_date` is bounded (today, and no older than 7 days).
 *   3. `amount` is required, integer, positive, capped at 99,999,999.
 *   4. `method` is an in-list: `physical_cash` or `bank_deposit`.
 *   5. `attachment_url` is REQUIRED — transfers always need an
 *      attachment (the receipt photo or the bank reference). The
 *      rule is unconditional.
 */
class StoreTransferRequestTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private int $poolA;

    private int $poolB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();

        $this->poolA = \App\Models\CashFlow\CashPool::first()->id;
        $this->poolB = \App\Models\CashFlow\CashPool::create([
            'name' => 'Secondary Pool',
            'type' => 'branch_cash',
            'location_id' => \App\Models\Locations::first()->id,
            'account_id' => \Illuminate\Support\Facades\Auth::user()->account_id,
            'is_active' => 1,
        ])->id;
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'transfer_date' => now()->toDateString(),
            'amount' => 1000,
            'from_pool_id' => $this->poolA,
            'to_pool_id' => $this->poolB,
            'method' => 'physical_cash',
            'attachment_url' => 'https://drive.google.com/file/d/1abc/view',
        ], $overrides);
    }

    private function validatorFor(array $payload): \Illuminate\Contracts\Validation\Validator
    {
        $request = new StoreTransferRequest;

        return Validator::make($payload, $request->rules(), $request->messages() ?? []);
    }

    public function test_valid_payload_passes(): void
    {
        $validator = $this->validatorFor($this->validPayload());
        $this->assertTrue(
            $validator->passes(),
            'Baseline payload should validate — errors: ' . $validator->errors()->first(),
        );
    }

    public function test_source_and_destination_pools_must_differ(): void
    {
        // A self-transfer would pass the exists checks but must be
        // blocked by the `different` rule.
        $validator = $this->validatorFor($this->validPayload(['to_pool_id' => $this->poolA]));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('to_pool_id', $validator->errors()->toArray());
    }

    public function test_method_must_be_physical_cash_or_bank_deposit(): void
    {
        $validator = $this->validatorFor($this->validPayload(['method' => 'cheque']));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('method', $validator->errors()->toArray());
    }

    public function test_attachment_url_is_required(): void
    {
        $validator = $this->validatorFor($this->validPayload(['attachment_url' => null]));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('attachment_url', $validator->errors()->toArray());
    }

    public function test_amount_rejects_decimals(): void
    {
        $validator = $this->validatorFor($this->validPayload(['amount' => 500.50]));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('amount', $validator->errors()->toArray());
    }

    public function test_transfer_date_cannot_be_future_dated(): void
    {
        $validator = $this->validatorFor($this->validPayload([
            'transfer_date' => now()->addDay()->toDateString(),
        ]));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('transfer_date', $validator->errors()->toArray());
    }

    public function test_source_pool_must_exist(): void
    {
        $validator = $this->validatorFor($this->validPayload(['from_pool_id' => 99_999]));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('from_pool_id', $validator->errors()->toArray());
    }
}
