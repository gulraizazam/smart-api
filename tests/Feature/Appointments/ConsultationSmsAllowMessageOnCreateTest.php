<?php

declare(strict_types=1);

namespace Tests\Feature\Appointments;

use App\Models\AppointmentStatuses;
use App\Models\AppointmentTypes;
use App\Models\Locations;
use App\Models\Patients;
use App\Models\Services;
use App\Models\User;
use App\Services\Appointment\ConsultancyService;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * The booking + reminder SMS crons gate on
 * `appointments.appointment_status_allow_message = 1`. That column defaults
 * to 0, so the consultation create flow MUST copy the chosen status's
 * `allow_message` onto the appointment — otherwise every API-created
 * consultation is silently un-sendable (the bug that surfaced once crm2,
 * whose create controllers set this field, was disconnected from the DB).
 *
 * Pinned in AppointmentHelper::prepareAppointmentData. Two cases prove the
 * value is DERIVED from the status, not hard-coded:
 *   - default Pending status (allow_message = 1) → field = 1 (+ send_message)
 *   - a status with allow_message = 0            → field = 0
 */
class ConsultationSmsAllowMessageOnCreateTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
    }

    private function consultancyTypeId(): int
    {
        $id = AppointmentTypes::query()->where('account_id', 1)->where('slug', 'consultancy')->value('id');
        $this->assertNotNull($id, 'Consultancy appointment type fixture missing.');

        return (int) $id;
    }

    private function allocateService(int $doctorId, int $locationId, int $serviceId): void
    {
        DB::table('doctor_has_locations')->insert([
            'user_id' => $doctorId,
            'location_id' => $locationId,
            'service_id' => $serviceId,
            'end_node' => 1,
            'is_allocated' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return array{0: int, 1: int, 2: int} [locationId, doctorId, serviceId] */
    private function bookingFixtures(): array
    {
        $location = Locations::factory()->create();
        $doctor = User::factory()->doctor()->create();
        $service = Services::factory()->create();
        $this->allocateService($doctor->id, $location->id, $service->id);

        return [$location->id, $doctor->id, $service->id];
    }

    public function test_create_derives_allow_message_one_from_a_messaging_status(): void
    {
        // A status that allows messaging (the real default Pending status is
        // one) must enable the appointment's SMS gate. Use an explicit status
        // so the test pins the derivation, not the fixture's seeded values.
        $status = AppointmentStatuses::factory()->create([
            'account_id' => 1,
            'allow_message' => 1,
        ]);

        [$locationId, $doctorId, $serviceId] = $this->bookingFixtures();
        $patient = Patients::factory()->create(['account_id' => 1, 'phone' => '3001234567']);

        $appt = app(ConsultancyService::class)->createConsultancy([
            'appointment_type_id' => $this->consultancyTypeId(),
            'appointment_status_id' => $status->id,
            'location_id' => $locationId,
            'doctor_id' => $doctorId,
            'service_id' => $serviceId,
            'patient_id' => $patient->id,
            'phone' => $patient->phone,
            'name' => 'Allow One',
            'gender' => 1,
        ]);

        $this->assertTrue(
            (bool) $appt->appointment_status_allow_message,
            'Consultation create must copy the status allow_message=1 onto the appointment, or the SMS cron skips it.',
        );
        // The booking SMS also needs send_message raised — pin the pair.
        $this->assertTrue((bool) $appt->send_message);
    }

    public function test_create_derives_allow_message_zero_from_a_silent_status(): void
    {
        // A status that disables outgoing messages must NOT enable SMS —
        // proves the field is derived from the status, not hard-coded to 1.
        $silent = AppointmentStatuses::factory()->create([
            'account_id' => 1,
            'allow_message' => 0,
        ]);

        [$locationId, $doctorId, $serviceId] = $this->bookingFixtures();
        $patient = Patients::factory()->create(['account_id' => 1, 'phone' => '3009998877']);

        $appt = app(ConsultancyService::class)->createConsultancy([
            'appointment_type_id' => $this->consultancyTypeId(),
            'appointment_status_id' => $silent->id,
            'location_id' => $locationId,
            'doctor_id' => $doctorId,
            'service_id' => $serviceId,
            'patient_id' => $patient->id,
            'phone' => $patient->phone,
            'name' => 'Allow Zero',
            'gender' => 1,
        ]);

        $this->assertFalse(
            (bool) $appt->appointment_status_allow_message,
            'A status with allow_message=0 must leave the appointment SMS gate off.',
        );
    }
}
