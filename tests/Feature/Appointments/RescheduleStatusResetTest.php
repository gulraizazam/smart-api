<?php

declare(strict_types=1);

namespace Tests\Feature\Appointments;

use App\Enums\AppointmentType;
use App\Models\AppointmentStatuses;
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
 * Rescheduling an appointment resets its workflow status back to the default
 * (Pending) — UNLESS it has already Arrived or Converted, which reflect real
 * progress and must survive a date change. Previously the schedule/reschedule
 * path (consultations + treatment schedule) carried the OLD status onto the new
 * date, and the treatment drag-drop path reset UNCONDITIONALLY (wiping Arrived).
 * Treatments have no Converted status; the rule reduces to "reset unless
 * Arrived" for them.
 */
class RescheduleStatusResetTest extends TestCase
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

        $this->seedShift();
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

    /** A status row for account 1 with the given flags. */
    private function makeStatus(string $name, array $flags = []): AppointmentStatuses
    {
        return AppointmentStatuses::factory()->create(array_merge([
            'name' => $name,
            'account_id' => 1,
            'allow_message' => 0,
        ], $flags));
    }

    private function booking(int $typeId, int $statusId): Appointments
    {
        return Appointments::factory()->create([
            'account_id' => 1,
            'appointment_type_id' => $typeId,
            'appointment_status_id' => $statusId,
            'base_appointment_status_id' => $statusId,
            'location_id' => $this->location->id,
            'doctor_id' => $this->doctor->id,
            'service_id' => $this->serviceModel->id,
            'scheduled_date' => self::DATE,
            'scheduled_time' => '12:00:00',
        ]);
    }

    private function reschedule(Appointments $appt, string $time = '14:00:00'): void
    {
        $this->service->scheduleAppointment($appt->id, [
            'start' => self::DATE.'T'.$time,
            'doctor_id' => $this->doctor->id,
            'location_id' => $this->location->id,
            'reschedule' => 1,
        ]);
    }

    private function baseStatusOf(int $apptId): int
    {
        return (int) Appointments::whereKey($apptId)->value('base_appointment_status_id');
    }

    /* ───────────────────────── Shared helper ───────────────────────── */

    public function test_helper_preserves_arrived_and_converted(): void
    {
        $arrived = $this->makeStatus('Arrived X', ['is_arrived' => 1]);
        $converted = $this->makeStatus('Converted X', ['is_converted' => 1]);
        $this->makeStatus('Pending X', ['is_default' => 1]);

        $this->assertSame([], AppointmentStatuses::resetStatusOnReschedule($arrived->id, 1));
        $this->assertSame([], AppointmentStatuses::resetStatusOnReschedule($converted->id, 1));
    }

    public function test_helper_resets_non_arrived_non_converted_to_default(): void
    {
        $pending = $this->makeStatus('Pending X', ['is_default' => 1]);
        $noShow = $this->makeStatus('No Show X');

        $result = AppointmentStatuses::resetStatusOnReschedule($noShow->id, 1);

        $this->assertSame($pending->id, $result['appointment_status_id']);
        $this->assertSame($pending->id, $result['base_appointment_status_id']);
    }

    public function test_helper_is_a_noop_when_no_default_status_is_configured(): void
    {
        // Account 1 has no is_default status here; a fresh account has none either.
        $this->assertSame([], AppointmentStatuses::resetStatusOnReschedule(null, 4242));
    }

    /* ─────────────────── Consultation (schedule path) ─────────────────── */

    public function test_consultation_reschedule_resets_no_show_to_pending(): void
    {
        $pending = $this->makeStatus('Pending', ['is_default' => 1]);
        $noShow = $this->makeStatus('No Show');
        $appt = $this->booking(AppointmentType::Consultancy->value, $noShow->id);

        $this->reschedule($appt);

        $this->assertSame($pending->id, $this->baseStatusOf($appt->id), 'A non-arrived/non-converted consultation must reset to Pending.');
    }

    public function test_consultation_reschedule_keeps_arrived(): void
    {
        $this->makeStatus('Pending', ['is_default' => 1]);
        $arrived = $this->makeStatus('Arrived', ['is_arrived' => 1]);
        $appt = $this->booking(AppointmentType::Consultancy->value, $arrived->id);

        $this->reschedule($appt);

        $this->assertSame($arrived->id, $this->baseStatusOf($appt->id), 'An Arrived consultation keeps its status on reschedule.');
    }

    public function test_consultation_reschedule_keeps_converted(): void
    {
        $this->makeStatus('Pending', ['is_default' => 1]);
        $converted = $this->makeStatus('Converted', ['is_converted' => 1]);
        $appt = $this->booking(AppointmentType::Consultancy->value, $converted->id);

        $this->reschedule($appt);

        $this->assertSame($converted->id, $this->baseStatusOf($appt->id), 'A Converted consultation keeps its status on reschedule.');
    }

    /* ──────────────────── Treatment (schedule path) ──────────────────── */

    public function test_treatment_reschedule_resets_no_show_to_pending(): void
    {
        $pending = $this->makeStatus('Pending', ['is_default' => 1]);
        $noShow = $this->makeStatus('No Show');
        $appt = $this->booking(AppointmentType::Treatment->value, $noShow->id);

        $this->reschedule($appt);

        $this->assertSame($pending->id, $this->baseStatusOf($appt->id), 'A non-arrived treatment must reset to Pending.');
    }

    public function test_treatment_reschedule_keeps_arrived(): void
    {
        $this->makeStatus('Pending', ['is_default' => 1]);
        $arrived = $this->makeStatus('Arrived', ['is_arrived' => 1]);
        $appt = $this->booking(AppointmentType::Treatment->value, $arrived->id);

        $this->reschedule($appt);

        $this->assertSame($arrived->id, $this->baseStatusOf($appt->id), 'An Arrived treatment keeps its status on reschedule.');
    }

    /* ────────────────── Treatment (drag-drop path) ────────────────── */

    private function dragDrop(Appointments $appt, string $time = '14:00:00'): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/treatments/drag-drop-reschedule', [
            'id' => $appt->id,
            'start' => self::DATE.' '.$time,
            'end' => self::DATE.' '.substr($time, 0, 2).':59:00',
            'doctor_id' => $this->doctor->id,
        ]);
    }

    public function test_treatment_drag_drop_resets_no_show_to_pending(): void
    {
        $pending = $this->makeStatus('Pending', ['is_default' => 1]);
        $noShow = $this->makeStatus('No Show');
        $appt = $this->booking(AppointmentType::Treatment->value, $noShow->id);

        $this->dragDrop($appt)->assertOk();

        $this->assertSame($pending->id, $this->baseStatusOf($appt->id), 'A non-arrived treatment drag must reset to Pending.');
    }

    public function test_treatment_drag_drop_keeps_arrived(): void
    {
        $this->makeStatus('Pending', ['is_default' => 1]);
        $arrived = $this->makeStatus('Arrived', ['is_arrived' => 1]);
        $appt = $this->booking(AppointmentType::Treatment->value, $arrived->id);

        $this->dragDrop($appt)->assertOk();

        $this->assertSame($arrived->id, $this->baseStatusOf($appt->id), 'An Arrived treatment drag keeps its status (was wiped before the fix).');
    }
}
