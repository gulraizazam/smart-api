<?php

declare(strict_types=1);

namespace Tests\Unit\Requests;

use App\Http\Requests\Admin\RefundCreateRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * RefundCreateRequest is the form-request behind the refund create
 * modal on the packages screen. Its validation is intentionally
 * strict because a refund is an irreversible cash-out event.
 *
 * Invariants:
 *   1. `refund_amount` is required, numeric, and restricted to the
 *      `^[0-9]+$` regex — WHOLE numbers only. A float refund was
 *      the audit's trigger to tighten this.
 *   2. `refund_note`, `package_id`, `payment_mode_id` are required
 *      — there is no "silent refund".
 *   3. `created_at` is a required Y-m-d date — the audit log
 *      timestamps the refund event from this field, not now().
 */
class RefundCreateRequestTest extends TestCase
{
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'refund_amount' => '500',
            'refund_note' => 'Customer requested refund',
            'package_id' => 1,
            'payment_mode_id' => 1,
            'created_at' => '2026-04-09',
        ], $overrides);
    }

    private function validatorFor(array $payload): \Illuminate\Contracts\Validation\Validator
    {
        return Validator::make($payload, (new RefundCreateRequest)->rules());
    }

    public function test_valid_payload_passes(): void
    {
        $this->assertTrue($this->validatorFor($this->validPayload())->passes());
    }

    public function test_refund_amount_rejects_decimals(): void
    {
        // The regex `^[0-9]+$` rejects any dot, so 500.50 fails.
        // Paisa-level refunds are not allowed by the current spec.
        $validator = $this->validatorFor($this->validPayload(['refund_amount' => '500.50']));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('refund_amount', $validator->errors()->toArray());
    }

    public function test_refund_amount_rejects_non_numeric(): void
    {
        $validator = $this->validatorFor($this->validPayload(['refund_amount' => 'abc']));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('refund_amount', $validator->errors()->toArray());
    }

    public function test_refund_note_is_required(): void
    {
        $validator = $this->validatorFor($this->validPayload(['refund_note' => null]));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('refund_note', $validator->errors()->toArray());
    }

    public function test_package_id_is_required(): void
    {
        $validator = $this->validatorFor($this->validPayload(['package_id' => null]));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('package_id', $validator->errors()->toArray());
    }

    public function test_payment_mode_id_is_required(): void
    {
        $validator = $this->validatorFor($this->validPayload(['payment_mode_id' => null]));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('payment_mode_id', $validator->errors()->toArray());
    }

    public function test_created_at_must_match_ymd_format(): void
    {
        // "April 9, 2026" parses as a valid date but fails the
        // strict Y-m-d format — this is the rule that stops a
        // locale-formatted string from reaching the controller.
        $validator = $this->validatorFor($this->validPayload(['created_at' => 'April 9, 2026']));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('created_at', $validator->errors()->toArray());
    }

    public function test_created_at_is_required(): void
    {
        $validator = $this->validatorFor($this->validPayload(['created_at' => null]));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('created_at', $validator->errors()->toArray());
    }
}
