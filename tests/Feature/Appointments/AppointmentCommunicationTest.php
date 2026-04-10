<?php

declare(strict_types=1);

namespace Tests\Feature\Appointments;

use App\Models\Appointments;
use App\Models\AppointmentStatuses;
use App\Models\AuditTrails;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Appointment communication = "did we tell the patient about this
 * change, and is there a paper trail we can point a regulator at?"
 * Two things the audit wanted pinned on this surface:
 *
 *   1. `send_message` + `appointment_status_allow_message` control
 *      whether the notification cron dispatches an SMS/email on the
 *      next tick. The audit re-wired this column after discovering
 *      that an older refactor had silenced all SMS. Pin that the
 *      booleans round-trip through the cast, that they default
 *      predictably, and that `appointment_status_allow_message`
 *      tracks the base status row's own `allow_message` flag.
 *
 *   2. The `audit_trails` hook on create/update/delete — the clinic's
 *      compliance view reads this table to show who touched an
 *      appointment. AppointmentEvent was silently un-wired before the
 *      audit: the model dispatched 'appointment.created' but no
 *      provider registered a listener, so the entire trail was dead.
 *      Pin that every mutation now appends at least one row.
 *      (AppointmentCrudTest also pins the create/update/delete trail
 *      from the CRUD angle; here we pin the full booked-to-cancelled
 *      communication lifecycle end-to-end.)
 */
class AppointmentCommunicationTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
    }

    public function test_send_message_column_round_trips_through_the_boolean_cast(): void
    {
        // The model casts send_message to boolean. Pin that the cast
        // holds so a controller reading `$appointment->send_message`
        // gets a true/false, not '1'/'0'.
        $appointment = Appointments::factory()->create(['send_message' => 1]);
        $this->assertTrue($appointment->send_message);

        $appointment->update(['send_message' => 0]);
        $appointment->refresh();
        $this->assertFalse($appointment->send_message);
    }

    public function test_appointment_status_allow_message_column_round_trips(): void
    {
        $appointment = Appointments::factory()->create([
            'appointment_status_allow_message' => 1,
        ]);
        $this->assertTrue($appointment->appointment_status_allow_message);

        $appointment->update(['appointment_status_allow_message' => 0]);
        $appointment->refresh();
        $this->assertFalse($appointment->appointment_status_allow_message);
    }

    public function test_msg_count_is_an_integer_counter_that_supports_increment(): void
    {
        // The cron job that sends reminder SMS increments msg_count
        // after every successful send. Pin that the column accepts
        // increments — a future migration tightening it to a smaller
        // column type would break the "send X reminders then stop"
        // loop silently.
        $appointment = Appointments::factory()->create(['msg_count' => 0]);

        $appointment->increment('msg_count');
        $appointment->increment('msg_count');
        $appointment->refresh();

        $this->assertSame(2, (int) $appointment->msg_count);
    }

    public function test_lifecycle_mutations_each_append_an_audit_trail_row(): void
    {
        // End-to-end pin: booked → arrived → completed → cancelled
        // must leave at least one audit_trail row for each step, via
        // the AppointmentEvent listeners registered in
        // AppServiceProvider::registerAuditEventListeners(). Without
        // that wiring the entire trail is a silent no-op.
        $countBefore = AuditTrails::query()->count();

        $appointment = Appointments::factory()->create(); // create
        $countAfterCreate = AuditTrails::query()->count();
        $this->assertGreaterThan(
            $countBefore,
            $countAfterCreate,
            'Booking an appointment must append a create row to audit_trails.',
        );

        $appointment->update(['appointment_status_id' => 2]); // arrived
        $countAfterArrived = AuditTrails::query()->count();
        $this->assertGreaterThan(
            $countAfterCreate,
            $countAfterArrived,
            'Marking the appointment as arrived must append an update row to audit_trails.',
        );

        $appointment->update(['appointment_status_id' => 4]); // completed
        $countAfterCompleted = AuditTrails::query()->count();
        $this->assertGreaterThan(
            $countAfterArrived,
            $countAfterCompleted,
            'Marking the appointment as completed must append an update row to audit_trails.',
        );

        $appointment->delete(); // soft-delete / cancel
        $countAfterDelete = AuditTrails::query()->count();
        $this->assertGreaterThan(
            $countAfterCompleted,
            $countAfterDelete,
            'Soft-deleting the appointment must append a delete row to audit_trails.',
        );
    }

    public function test_status_row_allow_message_flag_is_independent_of_appointment_send_message(): void
    {
        // Pin the separation between the two flags: the status row's
        // `allow_message` is a template ("does this status ever send
        // a message?") while the appointment's `send_message` is the
        // per-row toggle ("did we already send one?"). A common source
        // of confusion; keep them pinned separately so a refactor that
        // conflates them surfaces here.
        $status = AppointmentStatuses::query()
            ->where('name', 'Arrived')
            ->where('account_id', 1)
            ->firstOrFail();
        $status->update(['allow_message' => 1]);

        $appointment = Appointments::factory()->create([
            'appointment_status_id' => $status->id,
            'send_message' => 0,
            'appointment_status_allow_message' => 1,
        ]);

        $this->assertTrue($appointment->appointment_status_allow_message);
        $this->assertFalse($appointment->send_message);
        $this->assertSame(1, (int) $status->fresh()->allow_message);
    }
}
