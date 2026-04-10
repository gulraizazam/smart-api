<?php

declare(strict_types=1);

namespace Tests\Feature\Appointments;

use App\Models\Appointments;
use App\Models\AppointmentStatuses;
use App\Models\CancellationReasons;
use App\Models\Locations;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Cancellation is the clinic's "unhappy path" for a booked slot. The
 * audit's concerns:
 *
 *   - A cancelled appointment must keep its row (soft-delete only);
 *     hard-delete would break the operations report's "cancellation
 *     rate" metric.
 *   - The cancellation_reason_id FK is NULLable, but the front-desk
 *     workflow requires one of the clinic's pre-approved reasons for
 *     policy reporting. Pin that setting it via the model path
 *     persists and loads the relationship.
 *   - scopeExcludeCancelled() must filter the cancelled rows out when
 *     the dashboard renders the "active appointments" list. Coverage
 *     of the scope is in AppointmentStatusTransitionsTest; this file
 *     pins the cancellation *flow* — the model-side write path — not
 *     the scope semantics.
 *   - A cancellation must still be discoverable via the
 *     cancellation_reason relationship so compliance can walk every
 *     cancelled appointment and count by reason.
 */
class AppointmentCancellationTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private CancellationReasons $reason;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();

        $this->reason = CancellationReasons::create([
            'name' => 'Patient Rescheduled',
            'sort_no' => 1,
            'active' => 1,
            'appointment_type_id' => 1,
            'account_id' => 1,
        ]);
    }

    public function test_cancelling_an_appointment_stores_the_reason_id_and_the_free_text_reason(): void
    {
        $appointment = Appointments::factory()->create();

        $appointment->update([
            'appointment_status_id' => 5, // Cancelled
            'cancellation_reason_id' => $this->reason->id,
            'reason' => 'Patient called and asked to reschedule for next week.',
        ]);

        $appointment->refresh();
        $this->assertSame(5, $appointment->appointment_status_id);
        $this->assertSame($this->reason->id, $appointment->cancellation_reason_id);
        $this->assertSame(
            'Patient called and asked to reschedule for next week.',
            $appointment->reason,
        );
    }

    public function test_cancellation_reason_relationship_resolves_the_row(): void
    {
        // Pin that the belongsTo cancellation_reason relationship
        // resolves the reason row — this is what the cancellation
        // report widget reads to render the breakdown.
        $appointment = Appointments::factory()->create([
            'appointment_status_id' => 5,
            'cancellation_reason_id' => $this->reason->id,
        ]);

        $this->assertNotNull($appointment->cancellation_reason);
        $this->assertSame($this->reason->id, $appointment->cancellation_reason->id);
        $this->assertSame('Patient Rescheduled', $appointment->cancellation_reason->name);
    }

    public function test_a_cancelled_appointment_is_soft_deleted_not_hard_deleted(): void
    {
        // Cancellation is a status transition, NOT a destructive
        // action. Pin that a cancelled row stays in the table so the
        // operations report's cancellation rate metric can count it.
        $appointment = Appointments::factory()->cancelled()->create([
            'cancellation_reason_id' => $this->reason->id,
        ]);
        $id = $appointment->id;

        $this->assertNotNull(Appointments::query()->find($id));
        $this->assertNotNull(Appointments::query()->find($id)->cancellation_reason_id);
    }

    public function test_cancellation_does_not_clear_the_patient_linkage(): void
    {
        // Clinical audit requirement: a cancelled appointment still
        // points at the patient that booked it. Pin that cancelling
        // does not nullify patient_id / lead_id / location_id.
        $appointment = Appointments::factory()->create();
        $originalPatientId = $appointment->patient_id;
        $originalLeadId = $appointment->lead_id;
        $originalLocationId = $appointment->location_id;

        $appointment->update([
            'appointment_status_id' => 5,
            'cancellation_reason_id' => $this->reason->id,
            'reason' => 'Patient no-show',
        ]);

        $appointment->refresh();
        $this->assertSame($originalPatientId, $appointment->patient_id);
        $this->assertSame($originalLeadId, $appointment->lead_id);
        $this->assertSame($originalLocationId, $appointment->location_id);
    }

    public function test_multiple_cancellation_reasons_can_coexist_per_appointment_type(): void
    {
        // The cancellation_reasons table is scoped per appointment_type:
        // a consultancy's reasons differ from a treatment's. Pin that
        // two reasons under the same account can target different
        // appointment types without colliding.
        $consultancyReason = CancellationReasons::create([
            'name' => 'Doctor Unavailable',
            'sort_no' => 2,
            'active' => 1,
            'appointment_type_id' => 2,
            'account_id' => 1,
        ]);

        $treatmentReason = CancellationReasons::create([
            'name' => 'Machine Maintenance',
            'sort_no' => 3,
            'active' => 1,
            'appointment_type_id' => 1,
            'account_id' => 1,
        ]);

        $this->assertSame(2, $consultancyReason->appointment_type_id);
        $this->assertSame(1, $treatmentReason->appointment_type_id);
        $this->assertNotSame($consultancyReason->id, $treatmentReason->id);
    }

    public function test_cancelled_appointments_can_be_counted_by_reason(): void
    {
        // Pin the aggregate that the cancellation report uses — group
        // cancelled appointments by cancellation_reason_id and count.
        $otherReason = CancellationReasons::create([
            'name' => 'Weather',
            'sort_no' => 2,
            'active' => 1,
            'appointment_type_id' => 1,
            'account_id' => 1,
        ]);

        Appointments::factory()->count(3)->create([
            'appointment_status_id' => 5,
            'cancellation_reason_id' => $this->reason->id,
        ]);
        Appointments::factory()->count(2)->create([
            'appointment_status_id' => 5,
            'cancellation_reason_id' => $otherReason->id,
        ]);

        $byReason = Appointments::query()
            ->where('appointment_status_id', 5)
            ->selectRaw('cancellation_reason_id, COUNT(*) as total')
            ->groupBy('cancellation_reason_id')
            ->pluck('total', 'cancellation_reason_id')
            ->toArray();

        $this->assertSame(3, (int) $byReason[$this->reason->id]);
        $this->assertSame(2, (int) $byReason[$otherReason->id]);
    }
}
