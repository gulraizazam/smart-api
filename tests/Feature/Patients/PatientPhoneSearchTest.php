<?php

declare(strict_types=1);

namespace Tests\Feature\Patients;

use App\Models\Patients;
use App\Services\PatientManagement\PatientSearchService;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Consultations-list phone search (PatientSearchService::resolvePatientIdsByPhone)
 * must find a registered patient regardless of how their `phone` was stored —
 * INCLUDING rows whose `phone_normalized` was never populated.
 *
 * Regression (2026-06-05): `phone_normalized` is only filled by the crm3 model
 * hook, so legacy imports and (ongoing) crm2-created patients on the shared DB
 * land with a NULL `phone_normalized`. The indexed prefix/substring paths only
 * see that column, so those real patients returned "No consultations match"
 * when searched by number. A raw-`phone` fallback (via matchVariants, mirroring
 * Patients::getByPhone) closes the gap.
 */
class PatientPhoneSearchTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
    }

    /** Reproduce a crm2/legacy row: phone set, phone_normalized never filled. */
    private function patientWithNullNormalizedPhone(string $storedPhone): Patients
    {
        $p = Patients::factory()->create(['phone' => $storedPhone, 'account_id' => 1]);
        // The crm3 model hook populates phone_normalized on save; null it to
        // reproduce a row inserted outside that path (crm2 / raw import).
        DB::table('users')->where('id', $p->id)->update(['phone_normalized' => null]);

        return $p->refresh();
    }

    public function test_finds_patient_with_null_phone_normalized_stored_with_leading_zero(): void
    {
        $patient = $this->patientWithNullNormalizedPhone('03077168463');
        $this->assertNull($patient->phone_normalized, 'precondition: phone_normalized is blank');

        // Every way an operator might type the number resolves the patient.
        foreach (['03077168463', '3077168463', '923077168463', '+923077168463'] as $typed) {
            $ids = PatientSearchService::resolvePatientIdsByPhone($typed, 1);
            $this->assertContains(
                $patient->id,
                $ids,
                "Search for \"{$typed}\" must find the patient even with a blank phone_normalized.",
            );
        }
    }

    public function test_still_finds_patient_with_populated_phone_normalized(): void
    {
        // Control: the fast indexed path keeps working for normalised rows.
        $patient = Patients::factory()->create(['phone' => '3009998877', 'account_id' => 1]);
        $this->assertNotNull($patient->phone_normalized);

        $ids = PatientSearchService::resolvePatientIdsByPhone('3009998877', 1);
        $this->assertContains($patient->id, $ids);
    }

    public function test_blank_phone_normalized_does_not_over_match_a_different_number(): void
    {
        // The fallback must not turn into a catch-all: an unrelated number
        // must not surface this patient.
        $patient = $this->patientWithNullNormalizedPhone('03077168463');

        $ids = PatientSearchService::resolvePatientIdsByPhone('03211234567', 1);
        $this->assertNotContains($patient->id, $ids);
    }
}
