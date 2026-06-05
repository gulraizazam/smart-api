<?php

declare(strict_types=1);

namespace Tests\Feature\Treatments;

use App\Exceptions\TreatmentException;
use App\Models\Patients;
use App\Services\Treatment\TreatmentService;
use ReflectionMethod;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * The treatment SUBMIT resolves the patient by phone (treatments can't create
 * patients — that's reserved for consultations). It must find the patient
 * whether or not a leading 0 is present on the input OR in storage, otherwise
 * a patient the consultation already registered reads as "no registered
 * patient with this phone" on the treatment screen.
 */
class TreatmentPatientResolutionTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
    }

    private function resolve(array $data, int $accountId = 1): int
    {
        $method = new ReflectionMethod(TreatmentService::class, 'resolveExistingPatientOrFail');
        $method->setAccessible(true);

        return $method->invoke(app(TreatmentService::class), $data, $accountId);
    }

    public function test_resolves_patient_stored_with_leading_zero_for_any_input(): void
    {
        $patient = Patients::factory()->create(['phone' => '03110088221', 'account_id' => 1]);

        foreach (['3110088221', '03110088221', '923110088221', '+92 311 0088221'] as $input) {
            $this->assertSame($patient->id, $this->resolve(['phone' => $input]), "Input '{$input}' must resolve.");
        }
    }

    public function test_resolves_patient_stored_normalized_for_any_input(): void
    {
        $patient = Patients::factory()->create(['phone' => '3215044424', 'account_id' => 1]);

        foreach (['3215044424', '03215044424'] as $input) {
            $this->assertSame($patient->id, $this->resolve(['phone' => $input]), "Input '{$input}' must resolve.");
        }
    }

    public function test_resolves_patient_stored_with_country_code_prefix(): void
    {
        // Mirrors PatientGetByPhone: the stored row kept the 92 country code.
        // resolveExistingPatientOrFail relies on matchVariants to generate the
        // "92"+clean form — a plain cleaned / leading-0 match would miss it and
        // wrongly reject the treatment as "patient not registered". Pins the
        // merge resolution that kept matchVariants over the narrower variant.
        $patient = Patients::factory()->create(['phone' => '923110088221', 'account_id' => 1]);

        foreach (['3110088221', '03110088221', '923110088221', '+92 311 0088221'] as $input) {
            $this->assertSame($patient->id, $this->resolve(['phone' => $input]), "Input '{$input}' must resolve.");
        }
    }

    public function test_still_rejects_a_truly_unregistered_phone(): void
    {
        Patients::factory()->create(['phone' => '3215044424', 'account_id' => 1]);

        $this->expectException(TreatmentException::class);
        $this->resolve(['phone' => '3009998887']);
    }
}
