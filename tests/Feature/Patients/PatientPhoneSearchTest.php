<?php

declare(strict_types=1);

namespace Tests\Feature\Patients;

use App\Models\Patients;
use App\Services\PatientManagement\PatientSearchService;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Consultations-list phone search (PatientSearchService::resolvePatientIdsByPhone)
 * must find a registered patient regardless of how their `phone` was stored.
 *
 * History: `phone_normalized` was originally only filled by the crm3 model
 * hook, so crm2-created / legacy-imported patients on the shared DB landed with
 * a NULL `phone_normalized` and were invisible to the indexed phone search.
 *
 * As of the 2026_06_05 DB-level auto-fill trigger
 * (add_users_phone_normalized_autofill_trigger), `phone_normalized` is now
 * populated for EVERY write — including crm2's — so the null case no longer
 * occurs. The residual case the search still has to handle is the *leading
 * zero*: the column is digits-only, so a patient whose phone is `03077168463`
 * stores `phone_normalized = 03077168463`, and an operator typing the cleaned
 * `3077168463` must still resolve them (the substring tier, with the raw-phone
 * fallback kept as defence-in-depth). These tests pin that.
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

    public function test_finds_leading_zero_patient_by_every_typed_form(): void
    {
        // Front desk saved the number as typed, with the trunk 0. The hook +
        // the DB trigger store phone_normalized digits-only, so it keeps the
        // leading zero — the column is populated, not blank.
        $patient = Patients::factory()->create(['phone' => '03077168463', 'account_id' => 1]);
        $this->assertSame(
            '03077168463',
            $patient->phone_normalized,
            'precondition: normalized is populated (digits-only) and keeps the leading zero',
        );

        // Every way an operator might type the number resolves the patient,
        // including the cleaned form that doesn't prefix-match the stored
        // leading-zero value.
        foreach (['03077168463', '3077168463', '923077168463', '+923077168463'] as $typed) {
            $ids = PatientSearchService::resolvePatientIdsByPhone($typed, 1);
            $this->assertContains(
                $patient->id,
                $ids,
                "Search for \"{$typed}\" must resolve the leading-zero patient.",
            );
        }
    }

    public function test_still_finds_patient_with_canonical_phone_normalized(): void
    {
        // Control: the fast indexed prefix path keeps working for a number
        // stored without a leading zero (the canonical majority).
        $patient = Patients::factory()->create(['phone' => '3009998877', 'account_id' => 1]);
        $this->assertSame('3009998877', $patient->phone_normalized);

        $ids = PatientSearchService::resolvePatientIdsByPhone('3009998877', 1);
        $this->assertContains($patient->id, $ids);
    }

    public function test_does_not_over_match_a_different_number(): void
    {
        // The leading-zero handling must not turn into a catch-all: an
        // unrelated number must not surface this patient.
        $patient = Patients::factory()->create(['phone' => '03077168463', 'account_id' => 1]);

        $ids = PatientSearchService::resolvePatientIdsByPhone('03211234567', 1);
        $this->assertNotContains($patient->id, $ids);
    }
}
