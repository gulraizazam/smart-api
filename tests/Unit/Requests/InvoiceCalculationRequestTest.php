<?php

declare(strict_types=1);

namespace Tests\Unit\Requests;

use App\Http\Requests\Admin\InvoiceCalculationRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * InvoiceCalculationRequest is shared across three finance admin
 * endpoints (calculateAmounts, exportExemptInvoices,
 * downloadInvoicesZip). Its shape is the implicit contract for the
 * finance team — a silent regression here would dump malformed
 * parameters into the generator and produce wrong totals.
 *
 * Invariants:
 *   1. `consultation_amount` is gated to one of two legal values
 *      (1500 or 2000) — there is no third consultation tier.
 *   2. `bank_taxable` and `cash_percent` are percentages (0–100).
 *   3. `location_ids` is a required array of integers.
 *   4. `max_invoices_per_day` is an optional integer cap, 1–10.
 */
class InvoiceCalculationRequestTest extends TestCase
{
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'date_range' => '2026-01-01 - 2026-01-31',
            'location_ids' => [1, 2],
            'bank_taxable' => 10,
            'cash_percent' => 5,
            'consultation_amount' => 1500,
            'tax_percent' => 16,
            'max_invoices_per_day' => 5,
        ], $overrides);
    }

    private function validatorFor(array $payload): \Illuminate\Contracts\Validation\Validator
    {
        return Validator::make($payload, (new InvoiceCalculationRequest)->rules());
    }

    public function test_valid_payload_passes(): void
    {
        $this->assertTrue($this->validatorFor($this->validPayload())->passes());
    }

    public function test_consultation_amount_must_be_one_of_the_two_legal_tiers(): void
    {
        // Any value outside {1500, 2000} is rejected — the generator
        // only supports these two tiers.
        $validator = $this->validatorFor($this->validPayload(['consultation_amount' => 1800]));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('consultation_amount', $validator->errors()->toArray());
    }

    public function test_consultation_amount_accepts_2000(): void
    {
        $this->assertTrue(
            $this->validatorFor($this->validPayload(['consultation_amount' => 2000]))->passes(),
        );
    }

    public function test_bank_taxable_rejects_values_above_100(): void
    {
        $validator = $this->validatorFor($this->validPayload(['bank_taxable' => 101]));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('bank_taxable', $validator->errors()->toArray());
    }

    public function test_cash_percent_rejects_negative_values(): void
    {
        $validator = $this->validatorFor($this->validPayload(['cash_percent' => -5]));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('cash_percent', $validator->errors()->toArray());
    }

    public function test_location_ids_is_required(): void
    {
        $validator = $this->validatorFor($this->validPayload(['location_ids' => null]));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('location_ids', $validator->errors()->toArray());
    }

    public function test_location_ids_must_contain_integers(): void
    {
        $validator = $this->validatorFor($this->validPayload([
            'location_ids' => ['not-an-int'],
        ]));

        $this->assertTrue($validator->fails());
    }

    public function test_max_invoices_per_day_is_capped_at_10(): void
    {
        $validator = $this->validatorFor($this->validPayload(['max_invoices_per_day' => 11]));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('max_invoices_per_day', $validator->errors()->toArray());
    }

    public function test_tax_percent_is_optional(): void
    {
        $this->assertTrue(
            $this->validatorFor($this->validPayload(['tax_percent' => null]))->passes(),
        );
    }

    public function test_location_ids_rejects_empty_array(): void
    {
        $validator = $this->validatorFor($this->validPayload(['location_ids' => []]));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('location_ids', $validator->errors()->toArray());
    }

    public function test_date_range_is_required(): void
    {
        $validator = $this->validatorFor($this->validPayload(['date_range' => null]));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('date_range', $validator->errors()->toArray());
    }

    public function test_max_invoices_per_day_must_be_at_least_one(): void
    {
        $validator = $this->validatorFor($this->validPayload(['max_invoices_per_day' => 0]));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('max_invoices_per_day', $validator->errors()->toArray());
    }
}
