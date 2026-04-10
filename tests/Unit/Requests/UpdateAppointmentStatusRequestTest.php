<?php

declare(strict_types=1);

namespace Tests\Unit\Requests;

use App\Http\Requests\Appointment\UpdateAppointmentStatusRequest;
use Illuminate\Support\Facades\Validator;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * UpdateAppointmentStatusRequest gates the status-change endpoint on
 * the appointment screen. A regression here would let the UI post a
 * non-existent status id and silently soft-fail the state machine.
 *
 * Invariants:
 *   1. `appointment_status_id` is REQUIRED and must exist.
 *   2. `cancellation_reason_id` is optional but if provided must
 *      reference an existing row — prevents an operator fabricating
 *      a cancellation reason via a crafted payload.
 *   3. `reason` is a free-text field with no length cap (the
 *      audit log field itself is a TEXT column).
 *   4. `send_message` is boolean-ish.
 */
class UpdateAppointmentStatusRequestTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private int $statusId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();

        $this->statusId = \App\Models\AppointmentStatuses::first()->id;
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'appointment_status_id' => $this->statusId,
            'reason' => 'Patient requested reschedule',
            'send_message' => true,
        ], $overrides);
    }

    private function validatorFor(array $payload): \Illuminate\Contracts\Validation\Validator
    {
        $request = new UpdateAppointmentStatusRequest;

        return Validator::make($payload, $request->rules());
    }

    public function test_valid_payload_passes(): void
    {
        $this->assertTrue($this->validatorFor($this->validPayload())->passes());
    }

    public function test_appointment_status_id_is_required(): void
    {
        $validator = $this->validatorFor($this->validPayload(['appointment_status_id' => null]));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('appointment_status_id', $validator->errors()->toArray());
    }

    public function test_appointment_status_id_must_exist(): void
    {
        $validator = $this->validatorFor($this->validPayload(['appointment_status_id' => 99_999]));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('appointment_status_id', $validator->errors()->toArray());
    }

    public function test_cancellation_reason_id_must_exist_when_provided(): void
    {
        $validator = $this->validatorFor($this->validPayload(['cancellation_reason_id' => 99_999]));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('cancellation_reason_id', $validator->errors()->toArray());
    }

    public function test_cancellation_reason_id_is_optional(): void
    {
        $this->assertTrue(
            $this->validatorFor($this->validPayload(['cancellation_reason_id' => null]))->passes(),
        );
    }

    public function test_reason_is_optional(): void
    {
        $this->assertTrue(
            $this->validatorFor($this->validPayload(['reason' => null]))->passes(),
        );
    }

    public function test_send_message_accepts_boolean_falsy_values(): void
    {
        $this->assertTrue(
            $this->validatorFor($this->validPayload(['send_message' => 0]))->passes(),
        );
    }
}
