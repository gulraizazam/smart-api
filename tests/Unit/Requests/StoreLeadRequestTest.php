<?php

declare(strict_types=1);

namespace Tests\Unit\Requests;

use App\Http\Requests\Lead\StoreLeadRequest;
use Illuminate\Support\Facades\Validator;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * StoreLeadRequest is the gate on the new-lead form. It enforces:
 *
 *   1. `name` is required (max 255 chars).
 *   2. `phone` is required, 10–15 chars.
 *   3. `gender` is required and one of 1 (male) or 2 (female).
 *   4. `city_id` is required and must reference a real city.
 *   5. `email` is optional but must validate as an email if given.
 *   6. `lead_source_id`, `service_id`, `location_id`, `referred_by`
 *      are optional but their FKs must be valid if supplied.
 *
 * The `prepareForValidation` hook replaces `phone === '***********'`
 * with `old_phone` — that's the masked-phone preservation path for
 * unprivileged operators.
 */
class StoreLeadRequestTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private int $cityId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();

        $this->cityId = \Illuminate\Support\Facades\DB::table('cities')->first()->id;
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Aisha',
            'phone' => '03001234567',
            'gender' => 2,
            'city_id' => $this->cityId,
            'email' => 'aisha@example.com',
        ], $overrides);
    }

    private function validatorFor(array $payload): \Illuminate\Contracts\Validation\Validator
    {
        return Validator::make($payload, (new StoreLeadRequest)->rules());
    }

    public function test_valid_payload_passes(): void
    {
        $this->assertTrue($this->validatorFor($this->validPayload())->passes());
    }

    public function test_name_is_required(): void
    {
        $validator = $this->validatorFor($this->validPayload(['name' => null]));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_phone_is_required(): void
    {
        $validator = $this->validatorFor($this->validPayload(['phone' => null]));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('phone', $validator->errors()->toArray());
    }

    public function test_phone_rejects_too_short(): void
    {
        $validator = $this->validatorFor($this->validPayload(['phone' => '12345']));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('phone', $validator->errors()->toArray());
    }

    public function test_phone_rejects_too_long(): void
    {
        $validator = $this->validatorFor($this->validPayload([
            'phone' => '1234567890123456', // 16 chars > 15 max
        ]));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('phone', $validator->errors()->toArray());
    }

    public function test_gender_is_required(): void
    {
        $validator = $this->validatorFor($this->validPayload(['gender' => null]));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('gender', $validator->errors()->toArray());
    }

    public function test_gender_must_be_1_or_2(): void
    {
        $validator = $this->validatorFor($this->validPayload(['gender' => 0]));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('gender', $validator->errors()->toArray());
    }

    public function test_city_id_is_required(): void
    {
        $validator = $this->validatorFor($this->validPayload(['city_id' => null]));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('city_id', $validator->errors()->toArray());
    }

    public function test_city_id_must_exist(): void
    {
        $validator = $this->validatorFor($this->validPayload(['city_id' => 99_999]));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('city_id', $validator->errors()->toArray());
    }

    public function test_email_must_be_a_valid_email_when_provided(): void
    {
        $validator = $this->validatorFor($this->validPayload(['email' => 'not-an-email']));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }

    public function test_email_is_optional(): void
    {
        $this->assertTrue(
            $this->validatorFor($this->validPayload(['email' => null]))->passes(),
        );
    }

    public function test_lead_source_id_must_exist_when_provided(): void
    {
        $validator = $this->validatorFor($this->validPayload(['lead_source_id' => 99_999]));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('lead_source_id', $validator->errors()->toArray());
    }

    public function test_service_id_must_exist_when_provided(): void
    {
        $validator = $this->validatorFor($this->validPayload(['service_id' => 99_999]));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('service_id', $validator->errors()->toArray());
    }

    public function test_location_id_must_exist_when_provided(): void
    {
        $validator = $this->validatorFor($this->validPayload(['location_id' => 99_999]));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('location_id', $validator->errors()->toArray());
    }
}
