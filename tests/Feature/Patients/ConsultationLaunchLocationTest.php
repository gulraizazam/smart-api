<?php

declare(strict_types=1);

namespace Tests\Feature\Patients;

use App\Models\AppointmentStatuses;
use App\Models\Appointments;
use App\Models\Locations;
use App\Models\Patients;
use App\Services\PatientManagement\PatientService;
use Illuminate\Support\Facades\Config;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Pins the contract behind the patient card's "New consultation" button:
 * it must open the booking calendar at the centre of the patient's last
 * ARRIVED consultation; falling back to their most recent consultation
 * of any status; and signalling "none" when the patient has no
 * consultation history.
 *
 * Discriminator in test_prefers_last_arrived_over_a_newer_unarrived: the
 * arrived consultation is deliberately OLDER than a later booked one, so
 * a naive "most recent consultation" implementation would return the
 * wrong centre and fail the assertion.
 */
class ConsultationLaunchLocationTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private PatientService $service;

    private int $consultancyTypeId;

    private int $treatmentTypeId;

    private int $arrivedStatusId;

    private int $bookedStatusId;

    private int $cancelledStatusId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->service = app(PatientService::class);

        $this->consultancyTypeId = (int) Config::get('constants.appointment_type_consultancy');
        $this->treatmentTypeId = (int) Config::get('constants.appointment_type_service');

        // Resolve status ids by name, never hard-code: appointment_statuses
        // autoincrement ids drift across the suite (rolled-back per-test
        // transactions don't reclaim ids in MariaDB), so a literal `2` is
        // only "Arrived" when this runs early in the suite. The shared
        // fixtures seed these names but don't flag is_arrived; production
        // does — set it on the resolved Arrived row.
        $this->bookedStatusId = (int) AppointmentStatuses::query()->where('name', 'Booked')->value('id');
        $this->cancelledStatusId = (int) AppointmentStatuses::query()->where('name', 'Cancelled')->value('id');
        $arrived = AppointmentStatuses::query()->where('name', 'Arrived')->firstOrFail();
        $arrived->forceFill(['is_arrived' => 1])->save();
        $this->arrivedStatusId = (int) $arrived->id;

        $this->actingAsAdmin();
    }

    public function test_prefers_last_arrived_over_a_newer_unarrived(): void
    {
        $patient = Patients::factory()->create();
        $arrivedCentre = Locations::factory()->create(['name' => 'Arrived Centre']);
        $bookedCentre = Locations::factory()->create(['name' => 'Booked Centre']);

        // Older — but ARRIVED.
        Appointments::factory()->create([
            'patient_id' => $patient->id,
            'appointment_type_id' => $this->consultancyTypeId,
            'appointment_status_id' => $this->arrivedStatusId,
            'location_id' => $arrivedCentre->id,
            'scheduled_date' => '2026-01-10',
        ]);

        // Newer — but only Booked.
        Appointments::factory()->create([
            'patient_id' => $patient->id,
            'appointment_type_id' => $this->consultancyTypeId,
            'appointment_status_id' => $this->bookedStatusId,
            'location_id' => $bookedCentre->id,
            'scheduled_date' => '2026-03-20',
        ]);

        $result = $this->service->consultationLaunchLocation((int) $patient->id);

        $this->assertNotNull($result);
        $this->assertSame(
            (int) $arrivedCentre->id,
            $result['location_id'],
            'Must return the last ARRIVED consultation centre, not the newer unarrived one.',
        );
    }

    public function test_falls_back_to_latest_consultation_when_none_arrived(): void
    {
        $patient = Patients::factory()->create();
        $oldCentre = Locations::factory()->create(['name' => 'Old Centre']);
        $latestCentre = Locations::factory()->create(['name' => 'Latest Centre']);

        Appointments::factory()->create([
            'patient_id' => $patient->id,
            'appointment_type_id' => $this->consultancyTypeId,
            'appointment_status_id' => $this->bookedStatusId,
            'location_id' => $oldCentre->id,
            'scheduled_date' => '2026-01-05',
        ]);
        Appointments::factory()->create([
            'patient_id' => $patient->id,
            'appointment_type_id' => $this->consultancyTypeId,
            'appointment_status_id' => $this->cancelledStatusId, // still not arrived
            'location_id' => $latestCentre->id,
            'scheduled_date' => '2026-04-01',
        ]);

        $result = $this->service->consultationLaunchLocation((int) $patient->id);

        $this->assertNotNull($result);
        $this->assertSame((int) $latestCentre->id, $result['location_id']);
    }

    public function test_ignores_treatment_appointments(): void
    {
        $patient = Patients::factory()->create();
        $consultCentre = Locations::factory()->create(['name' => 'Consult Centre']);
        $treatmentCentre = Locations::factory()->create(['name' => 'Treatment Centre']);

        // A consultation (older).
        Appointments::factory()->create([
            'patient_id' => $patient->id,
            'appointment_type_id' => $this->consultancyTypeId,
            'appointment_status_id' => $this->bookedStatusId,
            'location_id' => $consultCentre->id,
            'scheduled_date' => '2026-02-01',
        ]);
        // A newer TREATMENT — must be ignored by the consultation launcher.
        Appointments::factory()->create([
            'patient_id' => $patient->id,
            'appointment_type_id' => $this->treatmentTypeId,
            'appointment_status_id' => $this->arrivedStatusId,
            'location_id' => $treatmentCentre->id,
            'scheduled_date' => '2026-05-01',
        ]);

        $result = $this->service->consultationLaunchLocation((int) $patient->id);

        $this->assertNotNull($result);
        $this->assertSame((int) $consultCentre->id, $result['location_id']);
    }

    public function test_returns_null_when_patient_has_no_consultation(): void
    {
        $patient = Patients::factory()->create();

        $this->assertNull(
            $this->service->consultationLaunchLocation((int) $patient->id),
        );
    }

    public function test_last_appointment_at_is_emitted_in_pkt(): void
    {
        $patient = Patients::factory()->create();
        $centre = Locations::factory()->create(['name' => 'PKT Centre']);

        Appointments::factory()->create([
            'patient_id' => $patient->id,
            'appointment_type_id' => $this->consultancyTypeId,
            'appointment_status_id' => $this->arrivedStatusId,
            'location_id' => $centre->id,
            'scheduled_date' => '2026-01-10',
        ]);

        $result = $this->service->consultationLaunchLocation((int) $patient->id);

        $this->assertNotNull($result);
        // Emitted in PKT (+05:00), NOT shifted to UTC (which would roll the
        // date back to 2026-01-09T19:00:00+00:00).
        $this->assertStringStartsWith('2026-01-10T', $result['last_appointment_at']);
        $this->assertStringEndsWith('+05:00', $result['last_appointment_at']);
    }
}
