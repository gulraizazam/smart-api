<?php

declare(strict_types=1);

namespace Tests\Feature\Patients;

use App\Models\Appointments;
use App\Models\Locations;
use App\Models\Medical;
use App\Models\Patients;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * The medicals table is the join from a custom form feedback (the
 * actual filled-in form data) to the patient who filled it out and
 * the appointment it was filled out at. The audit's concern with
 * this surface is the join integrity:
 *
 *   - Cross-patient leakage: a medicals row for patient A must NOT
 *     surface when querying patient B's history. The IDOR sweep
 *     hinged on this; the front-end medical history widget reads
 *     ->where('patient_id', $id) and trusts the result.
 *   - FK integrity: medicals.patient_id, .appointment_id, and
 *     .custom_form_feedback_id are all NOT NULL FKs at the schema
 *     level. Pin that omitting any of them throws.
 *   - Survival of soft-deleted patients: clinical compliance requires
 *     that medical history remain readable even after the patient row
 *     is soft-deleted, so a regulator can audit a discharged patient's
 *     records. Pin that medicals do NOT cascade away on patient
 *     soft-delete.
 */
class PatientMedicalHistoryTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private Locations $location;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
        $this->location = Locations::factory()->create();
    }

    public function test_medicals_row_links_back_to_the_patient_via_patient_id(): void
    {
        $patient = Patients::factory()->create();
        $appointment = Appointments::factory()->create([
            'patient_id' => $patient->id,
            'location_id' => $this->location->id,
        ]);
        $feedbackId = $this->seedCustomFormFeedback();

        $medical = Medical::create([
            'user_id' => auth()->id(),
            'patient_id' => $patient->id,
            'appointment_id' => $appointment->id,
            'custom_form_feedback_id' => $feedbackId,
            'date' => now()->toDateString(),
        ]);

        $this->assertNotNull($medical->id);
        $this->assertSame($patient->id, $medical->patient->id, 'medical->patient must resolve.');
    }

    public function test_querying_patient_a_medicals_does_not_return_patient_b_rows(): void
    {
        // Pin the IDOR-relevant property: the per-patient medical
        // history widget must scope by patient_id and never spill
        // another patient's rows into the result set.
        $patientA = Patients::factory()->create();
        $patientB = Patients::factory()->create();

        $aptA = Appointments::factory()->create([
            'patient_id' => $patientA->id,
            'location_id' => $this->location->id,
        ]);
        $aptB = Appointments::factory()->create([
            'patient_id' => $patientB->id,
            'location_id' => $this->location->id,
        ]);

        $feedbackA = $this->seedCustomFormFeedback();
        $feedbackB = $this->seedCustomFormFeedback();

        Medical::create([
            'user_id' => auth()->id(),
            'patient_id' => $patientA->id,
            'appointment_id' => $aptA->id,
            'custom_form_feedback_id' => $feedbackA,
            'date' => now()->toDateString(),
        ]);
        Medical::create([
            'user_id' => auth()->id(),
            'patient_id' => $patientB->id,
            'appointment_id' => $aptB->id,
            'custom_form_feedback_id' => $feedbackB,
            'date' => now()->toDateString(),
        ]);

        $aOnly = Medical::query()->where('patient_id', $patientA->id)->get();

        $this->assertCount(1, $aOnly);
        $this->assertSame(
            $patientA->id,
            $aOnly->first()->patient_id,
            'Querying medicals for patient A must not surface patient B rows.'
        );
    }

    public function test_medicals_survive_a_patient_soft_delete_for_compliance_history(): void
    {
        // Clinical compliance: medical history must remain readable
        // even after the patient row is soft-deleted. Pin that the
        // medicals row stays when the patient is soft-deleted.
        $patient = Patients::factory()->create();
        $appointment = Appointments::factory()->create([
            'patient_id' => $patient->id,
            'location_id' => $this->location->id,
        ]);
        $feedbackId = $this->seedCustomFormFeedback();

        $medical = Medical::create([
            'user_id' => auth()->id(),
            'patient_id' => $patient->id,
            'appointment_id' => $appointment->id,
            'custom_form_feedback_id' => $feedbackId,
            'date' => now()->toDateString(),
        ]);

        $patient->delete();

        $stillThere = Medical::query()->find($medical->id);
        $this->assertNotNull(
            $stillThere,
            'medicals row must survive a patient soft-delete — clinical compliance.'
        );
        $this->assertSame($patient->id, $stillThere->patient_id);
    }

    public function test_medicals_insert_throws_when_required_fk_is_missing(): void
    {
        // Schema-level NOT NULL pin: every medicals row must declare
        // patient_id, appointment_id, custom_form_feedback_id, user_id.
        // Omitting custom_form_feedback_id must surface as a DB error,
        // not silently succeed with a null FK.
        $patient = Patients::factory()->create();
        $appointment = Appointments::factory()->create([
            'patient_id' => $patient->id,
            'location_id' => $this->location->id,
        ]);

        try {
            Medical::create([
                'user_id' => auth()->id(),
                'patient_id' => $patient->id,
                'appointment_id' => $appointment->id,
                // 'custom_form_feedback_id' => intentionally missing
                'date' => now()->toDateString(),
            ]);
            $this->fail('Medical insert must reject a missing custom_form_feedback_id.');
        } catch (\Illuminate\Database\QueryException $e) {
            // Either NOT NULL or FK constraint violation, both acceptable.
            $this->assertTrue(true);
        }
    }

    /**
     * Seed a minimal custom_forms + custom_form_feedbacks chain
     * sufficient to satisfy the medicals.custom_form_feedback_id FK.
     * Returns the feedback id.
     */
    private function seedCustomFormFeedback(): int
    {
        $formId = DB::table('custom_forms')->insertGetId([
            'name' => 'Medical History Form',
            'account_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('custom_form_feedbacks')->insertGetId([
            'form_name' => 'Medical History Submission',
            'custom_form_id' => $formId,
            'account_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
