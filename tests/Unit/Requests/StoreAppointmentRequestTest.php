<?php

declare(strict_types=1);

namespace Tests\Unit\Requests;

use App\Http\Requests\Appointment\StoreAppointmentRequest;
use Illuminate\Support\Facades\Validator;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * StoreAppointmentRequest is interesting because most of its fields
 * are `nullable` or `sometimes` — the schema is permissive at the
 * validation layer and the scheduling logic lives downstream in
 * AppointmentService. The few things the request DOES enforce:
 *
 *   1. `consultancy_type` is gated to `in_person` or `online`.
 *   2. `gender` is one of `0`, `1`, `2` (unspecified/male/female).
 *   3. `scheduled_time` must match `H:i:s` (after the
 *      prepareForValidation normalises h:mm A → H:i:s).
 *   4. `email` must validate as an email.
 *
 * The `exists:` rules make this test hit the DB, but most
 * assertions here only exercise the rule strings themselves.
 */
class StoreAppointmentRequestTest extends TestCase
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
        return array_merge([
            'scheduled_date' => '2026-05-01',
            'scheduled_time' => '14:30:00',
            'name' => 'Test Patient',
            'email' => 'test@example.com',
            'phone' => '03001234567',
            'gender' => 1,
            'consultancy_type' => 'in_person',
            'appointment_type' => 'consulting',
        ], $overrides);
    }

    private function validatorFor(array $payload): \Illuminate\Contracts\Validation\Validator
    {
        $request = new StoreAppointmentRequest;

        return Validator::make($payload, $request->rules());
    }

    public function test_valid_payload_passes(): void
    {
        $this->assertTrue($this->validatorFor($this->validPayload())->passes());
    }

    public function test_consultancy_type_must_be_in_person_or_online(): void
    {
        $validator = $this->validatorFor($this->validPayload(['consultancy_type' => 'hybrid']));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('consultancy_type', $validator->errors()->toArray());
    }

    public function test_consultancy_type_accepts_online(): void
    {
        $this->assertTrue(
            $this->validatorFor($this->validPayload(['consultancy_type' => 'online']))->passes(),
        );
    }

    public function test_gender_must_be_0_1_or_2(): void
    {
        $validator = $this->validatorFor($this->validPayload(['gender' => 5]));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('gender', $validator->errors()->toArray());
    }

    public function test_email_must_be_a_valid_email(): void
    {
        $validator = $this->validatorFor($this->validPayload(['email' => 'not-an-email']));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }

    public function test_scheduled_time_must_match_h_i_s_format(): void
    {
        // After the controller's prepareForValidation runs the
        // h:mm A → H:i:s conversion, the stored value must match
        // the strict time format. A string like "2:30 PM" (not
        // converted) must fail.
        $validator = $this->validatorFor($this->validPayload(['scheduled_time' => '2:30 PM']));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('scheduled_time', $validator->errors()->toArray());
    }

    public function test_referred_by_must_be_integer_when_provided(): void
    {
        $validator = $this->validatorFor($this->validPayload(['referred_by' => 'not-int']));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('referred_by', $validator->errors()->toArray());
    }

    public function test_email_is_nullable(): void
    {
        $this->assertTrue(
            $this->validatorFor($this->validPayload(['email' => null]))->passes(),
        );
    }
}
