<?php

declare(strict_types=1);

namespace Tests\Feature\Appointments;

use App\Enums\AppointmentType;
use App\Models\Appointments;
use App\Models\Locations;
use App\Models\Services;
use App\Models\User;
use App\Services\Appointment\AppointmentService;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Reschedule SMS re-arming, pinned for the shared core both the
 * consultation Schedule dialog / edit form and the treatment Schedule
 * dialog flow through (AppointmentService::scheduleAppointment and
 * ::updateAppointment).
 *
 * Product rule:
 *   - A reschedule that moves the DATE re-raises `send_message` so the
 *     same cron + active-template pipeline re-notifies the patient,
 *     exactly as it did on the original booking.
 *   - A time-only move (same date, different time) does NOT re-notify.
 *   - An already-Arrived/Converted appointment (base status != Booked)
 *     never re-notifies, even on a date move.
 *
 * Treatment-type appointments are used here so the consultancy-only rota
 * guard is skipped — the send_message decision under test is identical
 * for both types.
 */
class RescheduleSmsTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private AppointmentService $service;

    private Locations $location;

    private User $doctor;

    private Services $serviceModel;

    /** A weekday window so the working-day guard always passes. */
    private const ORIG_DATE = '2026-06-15'; // Monday
    private const ORIG_TIME = '10:00:00';
    private const NEW_DATE = '2026-06-16';  // Tuesday

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();

        $this->service = app(AppointmentService::class);
        $this->location = Locations::factory()->create();
        $this->doctor = User::factory()->doctor()->create(['account_id' => 1]);
        $this->serviceModel = Services::factory()->create(['account_id' => 1]);

        // Allocate the doctor to the service at the location so the
        // doctor-has-service guard in the reschedule paths passes.
        DB::table('doctor_has_locations')->insert([
            'user_id' => $this->doctor->id,
            'location_id' => $this->location->id,
            'service_id' => $this->serviceModel->id,
            'end_node' => 1,
            'is_allocated' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function bookedTreatment(int $baseStatusId = 1): Appointments
    {
        return Appointments::factory()->create([
            'account_id' => 1,
            // Treatment type skips the consultancy-only rota guard. NB: the
            // enum is Consultancy=1 / Treatment=2 (independent of the seeded
            // appointment_types row names), and the guard keys off the enum.
            'appointment_type_id' => AppointmentType::Treatment->value,
            'appointment_status_id' => $baseStatusId,
            'base_appointment_status_id' => $baseStatusId,
            'location_id' => $this->location->id,
            'doctor_id' => $this->doctor->id,
            'service_id' => $this->serviceModel->id,
            'scheduled_date' => self::ORIG_DATE,
            'scheduled_time' => self::ORIG_TIME,
            'send_message' => 0,
        ]);
    }

    private function sendMessageOf(int $id): int
    {
        return (int) Appointments::query()->whereKey($id)->value('send_message');
    }

    /* ───────── scheduleAppointment (the Schedule dialog path) ───────── */

    public function test_schedule_rearms_sms_when_the_date_changes(): void
    {
        $appt = $this->bookedTreatment();

        $this->service->scheduleAppointment($appt->id, [
            'start' => self::NEW_DATE . 'T11:00:00',
            'doctor_id' => $this->doctor->id,
            'location_id' => $this->location->id,
        ]);

        $this->assertSame(1, $this->sendMessageOf($appt->id), 'A date move must re-arm the SMS.');
    }

    public function test_schedule_does_not_rearm_sms_for_a_time_only_move(): void
    {
        $appt = $this->bookedTreatment();

        // Same date, different time.
        $this->service->scheduleAppointment($appt->id, [
            'start' => self::ORIG_DATE . 'T14:30:00',
            'doctor_id' => $this->doctor->id,
            'location_id' => $this->location->id,
        ]);

        $this->assertSame(0, $this->sendMessageOf($appt->id), 'A time-only move must NOT re-arm the SMS.');
    }

    public function test_schedule_does_not_rearm_sms_for_a_non_booked_appointment(): void
    {
        // base status 2 = Arrived (seeded by UsesFinancialFixtures).
        $appt = $this->bookedTreatment(baseStatusId: 2);

        $this->service->scheduleAppointment($appt->id, [
            'start' => self::NEW_DATE . 'T11:00:00',
            'doctor_id' => $this->doctor->id,
            'location_id' => $this->location->id,
        ]);

        $this->assertSame(0, $this->sendMessageOf($appt->id), 'An Arrived appointment must never re-arm on reschedule.');
    }

    /* ───────── updateAppointment (the edit-form path) ───────── */

    public function test_update_rearms_sms_when_the_date_changes(): void
    {
        $appt = $this->bookedTreatment();

        $this->service->updateAppointment($appt->id, [
            'scheduled_date' => self::NEW_DATE,
            'scheduled_time' => '11:00:00',
        ]);

        $this->assertSame(1, $this->sendMessageOf($appt->id), 'A date move via edit must re-arm the SMS.');
    }

    public function test_update_does_not_rearm_sms_for_a_time_only_edit(): void
    {
        $appt = $this->bookedTreatment();

        // Same date, different time only.
        $this->service->updateAppointment($appt->id, [
            'scheduled_date' => self::ORIG_DATE,
            'scheduled_time' => '15:45:00',
        ]);

        $this->assertSame(0, $this->sendMessageOf($appt->id), 'A time-only edit must NOT re-arm the SMS.');
    }
}
