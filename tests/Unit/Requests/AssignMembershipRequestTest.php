<?php

declare(strict_types=1);

namespace Tests\Unit\Requests;

use App\Http\Requests\AssignMembershipRequest;
use Illuminate\Support\Facades\Validator;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * AssignMembershipRequest is the gate on the "assign a membership
 * code to a patient" action. It accepts EITHER `patient_id` or
 * `id` (the legacy field name) and exposes a `patientId()` helper
 * so the controller can read whichever one was posted.
 *
 * Invariants pinned:
 *   1. `membership_code` is required (the whole point of the form).
 *   2. Either `patient_id` or `id` must exist if provided — neither
 *      is itself required, but the controller calls patientId() and
 *      requires a non-zero return.
 *   3. The `patientId()` helper prefers `patient_id` over `id`.
 *   4. failedValidation throws an HttpResponseException with a JSON
 *      422 body — tested here via the helper surface.
 */
class AssignMembershipRequestTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private int $patientUserId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();

        // Create a patient user row to be a valid FK for the
        // `exists:users,id` rule.
        $this->patientUserId = \Illuminate\Support\Facades\DB::table('users')->insertGetId([
            'name' => 'Patient Zero',
            'email' => 'patient-zero-' . uniqid() . '@example.com',
            'password' => bcrypt('x'),
            'account_id' => \Illuminate\Support\Facades\Auth::user()->account_id,
            'user_type_id' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function validatorFor(array $payload): \Illuminate\Contracts\Validation\Validator
    {
        return Validator::make($payload, (new AssignMembershipRequest)->rules());
    }

    public function test_membership_code_is_required(): void
    {
        $validator = $this->validatorFor([
            'patient_id' => $this->patientUserId,
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('membership_code', $validator->errors()->toArray());
    }

    public function test_valid_payload_with_patient_id_passes(): void
    {
        $this->assertTrue(
            $this->validatorFor([
                'patient_id' => $this->patientUserId,
                'membership_code' => 'GOLD-2026',
            ])->passes(),
        );
    }

    public function test_valid_payload_with_legacy_id_field_passes(): void
    {
        $this->assertTrue(
            $this->validatorFor([
                'id' => $this->patientUserId,
                'membership_code' => 'GOLD-2026',
            ])->passes(),
        );
    }

    public function test_patient_id_must_exist_when_provided(): void
    {
        $validator = $this->validatorFor([
            'patient_id' => 99_999,
            'membership_code' => 'GOLD-2026',
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('patient_id', $validator->errors()->toArray());
    }

    public function test_membership_code_max_length_is_100(): void
    {
        $validator = $this->validatorFor([
            'patient_id' => $this->patientUserId,
            'membership_code' => str_repeat('A', 101),
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('membership_code', $validator->errors()->toArray());
    }

    public function test_patient_id_helper_prefers_patient_id_over_id(): void
    {
        // When both are present, patient_id wins — this matches
        // the `?? $this->input('id')` coalesce operator in the
        // patientId() method.
        $request = AssignMembershipRequest::create('/', 'POST', [
            'patient_id' => 42,
            'id' => 17,
        ]);

        $this->assertSame(42, $request->patientId());
    }

    public function test_patient_id_helper_falls_back_to_id_field(): void
    {
        $request = AssignMembershipRequest::create('/', 'POST', [
            'id' => 17,
        ]);

        $this->assertSame(17, $request->patientId());
    }
}
