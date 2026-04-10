<?php

declare(strict_types=1);

namespace Tests\Feature\Patients;

use App\Models\Appointments;
use App\Models\Locations;
use App\Models\Measurement;
use App\Models\Patients;
use App\Models\Services;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Measurements are the "before/after appointment" snapshots a clinic
 * captures (vitals, body composition, etc) tied to a service. Unlike
 * the medicals table, measurements carry an extra discriminator: the
 * `type` enum {Before Appointment, After Appointment} and `priority`
 * enum {Low/Medium/High priority}. The audit's concerns:
 *
 *   - The enum values are an exact-match contract — feeding any
 *     string outside the set must error, NOT silently coerce. Pin it
 *     so a future migration that expands the enum surfaces as a
 *     legitimate behaviour change.
 *   - Per-patient isolation (same as medicals).
 *   - service_id is NOT NULL — every measurement is tied to a
 *     specific service for the comparison reports to make sense.
 */
class PatientMeasurementTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private Locations $location;

    private Services $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
        $this->location = Locations::factory()->create();
        $this->service = Services::factory()->create();
    }

    public function test_measurement_persists_with_a_valid_priority_and_type(): void
    {
        $patient = Patients::factory()->create();
        $appointment = Appointments::factory()->create([
            'patient_id' => $patient->id,
            'location_id' => $this->location->id,
        ]);
        $feedbackId = $this->seedCustomFormFeedback();

        $measurement = Measurement::create([
            'user_id' => auth()->id(),
            'patient_id' => $patient->id,
            'appointment_id' => $appointment->id,
            'custom_form_feedback_id' => $feedbackId,
            'service_id' => $this->service->id,
            'priority' => 'High priority',
            'type' => 'Before Appointment',
            'date' => now()->toDateString(),
        ]);

        $this->assertNotNull($measurement->id);
        $this->assertSame('High priority', $measurement->priority);
        $this->assertSame('Before Appointment', $measurement->type);
    }

    public function test_measurement_rejects_an_unknown_priority_value(): void
    {
        // Schema-level enum pin: only Low/Medium/High priority are
        // accepted. A typo or a stray value MUST error, not coerce.
        $patient = Patients::factory()->create();
        $appointment = Appointments::factory()->create([
            'patient_id' => $patient->id,
            'location_id' => $this->location->id,
        ]);
        $feedbackId = $this->seedCustomFormFeedback();

        try {
            Measurement::create([
                'user_id' => auth()->id(),
                'patient_id' => $patient->id,
                'appointment_id' => $appointment->id,
                'custom_form_feedback_id' => $feedbackId,
                'service_id' => $this->service->id,
                'priority' => 'Critical priority', // not in enum
                'type' => 'Before Appointment',
                'date' => now()->toDateString(),
            ]);
            $this->fail('Measurement insert must reject a priority not in the enum.');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertTrue(true);
        }
    }

    public function test_measurement_rejects_an_unknown_type_value(): void
    {
        $patient = Patients::factory()->create();
        $appointment = Appointments::factory()->create([
            'patient_id' => $patient->id,
            'location_id' => $this->location->id,
        ]);
        $feedbackId = $this->seedCustomFormFeedback();

        try {
            Measurement::create([
                'user_id' => auth()->id(),
                'patient_id' => $patient->id,
                'appointment_id' => $appointment->id,
                'custom_form_feedback_id' => $feedbackId,
                'service_id' => $this->service->id,
                'priority' => 'High priority',
                'type' => 'During Appointment', // not in enum
                'date' => now()->toDateString(),
            ]);
            $this->fail('Measurement insert must reject a type not in the enum.');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertTrue(true);
        }
    }

    public function test_per_patient_query_does_not_leak_other_patient_measurements(): void
    {
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

        Measurement::create([
            'user_id' => auth()->id(),
            'patient_id' => $patientA->id,
            'appointment_id' => $aptA->id,
            'custom_form_feedback_id' => $feedbackA,
            'service_id' => $this->service->id,
            'priority' => 'High priority',
            'type' => 'Before Appointment',
            'date' => now()->toDateString(),
        ]);
        Measurement::create([
            'user_id' => auth()->id(),
            'patient_id' => $patientB->id,
            'appointment_id' => $aptB->id,
            'custom_form_feedback_id' => $feedbackB,
            'service_id' => $this->service->id,
            'priority' => 'Medium priority',
            'type' => 'After Appointment',
            'date' => now()->toDateString(),
        ]);

        $aOnly = Measurement::query()->where('patient_id', $patientA->id)->get();
        $this->assertCount(1, $aOnly);
        $this->assertSame($patientA->id, $aOnly->first()->patient_id);
    }

    public function test_before_and_after_measurements_are_distinguishable_by_type(): void
    {
        // The clinic's progress dashboards group measurements by type
        // to render before/after charts. Pin that two rows with the
        // same patient/service can coexist if the type differs.
        $patient = Patients::factory()->create();
        $appointment = Appointments::factory()->create([
            'patient_id' => $patient->id,
            'location_id' => $this->location->id,
        ]);
        $feedbackBefore = $this->seedCustomFormFeedback();
        $feedbackAfter = $this->seedCustomFormFeedback();

        Measurement::create([
            'user_id' => auth()->id(),
            'patient_id' => $patient->id,
            'appointment_id' => $appointment->id,
            'custom_form_feedback_id' => $feedbackBefore,
            'service_id' => $this->service->id,
            'priority' => 'Low priority',
            'type' => 'Before Appointment',
            'date' => now()->toDateString(),
        ]);
        Measurement::create([
            'user_id' => auth()->id(),
            'patient_id' => $patient->id,
            'appointment_id' => $appointment->id,
            'custom_form_feedback_id' => $feedbackAfter,
            'service_id' => $this->service->id,
            'priority' => 'Low priority',
            'type' => 'After Appointment',
            'date' => now()->toDateString(),
        ]);

        $before = Measurement::query()
            ->where('patient_id', $patient->id)
            ->where('type', 'Before Appointment')
            ->count();
        $after = Measurement::query()
            ->where('patient_id', $patient->id)
            ->where('type', 'After Appointment')
            ->count();

        $this->assertSame(1, $before);
        $this->assertSame(1, $after);
    }

    private function seedCustomFormFeedback(): int
    {
        $formId = DB::table('custom_forms')->insertGetId([
            'name' => 'Measurement Form',
            'account_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('custom_form_feedbacks')->insertGetId([
            'form_name' => 'Measurement Submission',
            'custom_form_id' => $formId,
            'account_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
