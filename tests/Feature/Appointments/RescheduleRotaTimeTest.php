<?php

declare(strict_types=1);

namespace Tests\Feature\Appointments;

use App\Enums\AppointmentType;
use App\Exceptions\AppointmentException;
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
 * The doctor-schedule TIME-of-day guard on reschedule (scheduleAppointment)
 * and edit (updateAppointment).
 *
 * Bug: those paths checked only that the doctor had a rota on the DATE, not
 * that the chosen TIME fell within the shift — so an appointment could be
 * moved past the doctor's end time (e.g. after 9 PM for an 11 AM–9 PM shift).
 * The create path already validated this; reschedule/edit now reuse it.
 *
 * Applies to BOTH consultancy and treatment. It stays permissive when the
 * doctor has no rota that day (treatments keep their off-rota flexibility),
 * but enforces the shift window when one IS defined.
 */
class RescheduleRotaTimeTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private AppointmentService $service;

    private Locations $location;

    private User $doctor;

    private Services $serviceModel;

    private const DATE = '2026-06-16'; // Tuesday — passes the working-day guard
    private const RESOURCE_TYPE_DOCTOR = 2; // config('constants.resource_doctor_type_id')

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();

        $this->service = app(AppointmentService::class);
        $this->location = Locations::factory()->create();
        $this->doctor = User::factory()->doctor()->create(['account_id' => 1]);
        $this->serviceModel = Services::factory()->create(['account_id' => 1]);

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

    /** Give the doctor an 11:00–21:00 shift on self::DATE at the location. */
    private function seedShift(string $start = '11:00', string $end = '21:00'): void
    {
        DB::table('resource_types')->updateOrInsert(
            ['id' => self::RESOURCE_TYPE_DOCTOR],
            ['name' => 'Doctor', 'slug' => 'doctor', 'active' => 1, 'created_at' => now(), 'updated_at' => now()],
        );

        $resourceId = DB::table('resources')->insertGetId([
            'account_id' => 1,
            'name' => 'Doc Res',
            'active' => 1,
            'resource_type_id' => self::RESOURCE_TYPE_DOCTOR,
            'external_id' => $this->doctor->id,
            'location_id' => $this->location->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $rotaId = DB::table('resource_has_rota')->insertGetId([
            'account_id' => 1,
            'resource_id' => $resourceId,
            'resource_type_id' => self::RESOURCE_TYPE_DOCTOR,
            'location_id' => $this->location->id,
            'start' => '2026-01-01',
            'end' => '2026-12-31',
            'active' => 1,
            'is_treatment' => 1,
            'is_consultancy' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('resource_has_rota_days')->insert([
            'resource_has_rota_id' => $rotaId,
            'date' => self::DATE,
            'start_time' => $start,
            'end_time' => $end,
            'start_timestamp' => self::DATE.' 00:00:00',
            'end_timestamp' => self::DATE.' 23:59:59',
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function booking(int $typeId): Appointments
    {
        return Appointments::factory()->create([
            'account_id' => 1,
            'appointment_type_id' => $typeId,
            'appointment_status_id' => 1,
            'base_appointment_status_id' => 1,
            'location_id' => $this->location->id,
            'doctor_id' => $this->doctor->id,
            'service_id' => $this->serviceModel->id,
            'scheduled_date' => self::DATE,
            'scheduled_time' => '12:00:00',
        ]);
    }

    private function reschedule(Appointments $appt, string $time): Appointments
    {
        return $this->service->scheduleAppointment($appt->id, [
            'start' => self::DATE.'T'.$time,
            'doctor_id' => $this->doctor->id,
            'location_id' => $this->location->id,
        ]);
    }

    /* ───────── Service-allocation guard is consultancy-only ───────── */

    /**
     * Treatment reschedule must NOT enforce doctor service-allocation (the
     * edit path never did). Otherwise a treatment whose service the doctor
     * isn't allocated threw "service not assigned" and masked the real rota
     * error. With the guard scoped to consultancy, a valid-time treatment
     * reschedule succeeds even when the service isn't allocated.
     */
    public function test_treatment_reschedule_skips_doctor_service_allocation(): void
    {
        $this->seedShift();
        // A treatment on a service the doctor is NOT allocated.
        $unallocated = Services::factory()->create(['account_id' => 1]);
        $appt = Appointments::factory()->create([
            'account_id' => 1,
            'appointment_type_id' => AppointmentType::Treatment->value,
            'appointment_status_id' => 1,
            'base_appointment_status_id' => 1,
            'location_id' => $this->location->id,
            'doctor_id' => $this->doctor->id,
            'service_id' => $unallocated->id,
            'scheduled_date' => self::DATE,
            'scheduled_time' => '12:00:00',
        ]);

        // Valid time → succeeds (was previously blocked by "service not assigned").
        $this->reschedule($appt, '14:00:00');
        $this->assertStringStartsWith('14:00', (string) Appointments::whereKey($appt->id)->value('scheduled_time'));
    }

    /* ───────── Consultation ───────── */

    public function test_consultation_reschedule_within_shift_succeeds(): void
    {
        $this->seedShift();
        $appt = $this->booking(AppointmentType::Consultancy->value);

        $this->reschedule($appt, '14:00:00');

        $this->assertStringStartsWith('14:00', (string) Appointments::whereKey($appt->id)->value('scheduled_time'));
    }

    public function test_consultation_reschedule_after_shift_end_is_rejected(): void
    {
        $this->seedShift();
        $appt = $this->booking(AppointmentType::Consultancy->value);

        $this->expectException(AppointmentException::class);
        $this->expectExceptionMessage('within available shifts');
        $this->reschedule($appt, '22:00:00');
    }

    public function test_consultation_edit_after_shift_end_is_rejected(): void
    {
        $this->seedShift();
        $appt = $this->booking(AppointmentType::Consultancy->value);

        $this->expectException(AppointmentException::class);
        $this->expectExceptionMessage('within available shifts');
        $this->service->updateAppointment($appt->id, [
            'scheduled_date' => self::DATE,
            'scheduled_time' => '22:00:00',
        ]);
    }

    /* ───────── Treatment ───────── */

    public function test_treatment_reschedule_after_shift_end_is_rejected(): void
    {
        $this->seedShift();
        $appt = $this->booking(AppointmentType::Treatment->value);

        $this->expectException(AppointmentException::class);
        $this->expectExceptionMessage('within available shifts');
        $this->reschedule($appt, '22:00:00');
    }

    public function test_treatment_edit_after_shift_end_is_rejected(): void
    {
        $this->seedShift();
        $appt = $this->booking(AppointmentType::Treatment->value);

        $this->expectException(AppointmentException::class);
        $this->expectExceptionMessage('within available shifts');
        $this->service->updateAppointment($appt->id, [
            'scheduled_date' => self::DATE,
            'scheduled_time' => '22:00:00',
        ]);
    }

    /**
     * Off-rota flexibility preserved: with NO shift defined that day, a
     * treatment can still be moved to any time (the widget is permissive).
     */
    public function test_treatment_with_no_rota_is_still_allowed_off_shift(): void
    {
        // No seedShift() — the doctor has no rota on self::DATE.
        $appt = $this->booking(AppointmentType::Treatment->value);

        $this->reschedule($appt, '22:00:00');

        $this->assertStringStartsWith('22:00', (string) Appointments::whereKey($appt->id)->value('scheduled_time'));
    }
}
