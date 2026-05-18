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
 * Treatment twin of ConsultationLaunchLocationTest — pins the contract
 * behind the patient card's "New treatment" button: open the booking
 * calendar at the centre of the patient's last ARRIVED treatment;
 * fall back to their most recent treatment of any status; signal "none"
 * when the patient has no treatment history. Crucially, it must NOT be
 * confused by consultation appointments (the two launchers are
 * type-scoped against the same appointments table).
 *
 * Status ids are resolved by name, never hard-coded: appointment_statuses
 * autoincrement ids drift across the suite (rolled-back transactions don't
 * reclaim ids in MariaDB), so a literal `2` only happens to be "Arrived"
 * when the test runs early. ConsultationLaunchLocationTest still hard-codes
 * ids and survives only by alphabetical luck — don't copy that here.
 *
 * Discriminator in test_prefers_last_arrived_over_a_newer_unarrived: the
 * arrived treatment is deliberately OLDER than a later booked one, so a
 * naive "most recent" implementation would return the wrong centre.
 */
class TreatmentLaunchLocationTest extends TestCase
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

        // Resolve by name — the fixtures seed these exact names on
        // whatever autoincrement ids the suite has reached by now.
        // (appointment_statuses has no `slug` column in the test schema;
        // the fixture's `slug` key is silently dropped.)
        $this->bookedStatusId = (int) AppointmentStatuses::query()->where('name', 'Booked')->value('id');
        $this->cancelledStatusId = (int) AppointmentStatuses::query()->where('name', 'Cancelled')->value('id');
        $arrived = AppointmentStatuses::query()->where('name', 'Arrived')->firstOrFail();
        // Production data flags the arrived row; the shared fixtures don't.
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
            'appointment_type_id' => $this->treatmentTypeId,
            'appointment_status_id' => $this->arrivedStatusId,
            'location_id' => $arrivedCentre->id,
            'scheduled_date' => '2026-01-10',
        ]);

        // Newer — but only Booked.
        Appointments::factory()->create([
            'patient_id' => $patient->id,
            'appointment_type_id' => $this->treatmentTypeId,
            'appointment_status_id' => $this->bookedStatusId,
            'location_id' => $bookedCentre->id,
            'scheduled_date' => '2026-03-20',
        ]);

        $result = $this->service->treatmentLaunchLocation((int) $patient->id);

        $this->assertNotNull($result);
        $this->assertSame(
            (int) $arrivedCentre->id,
            $result['location_id'],
            'Must return the last ARRIVED treatment centre, not the newer unarrived one.',
        );
    }

    public function test_falls_back_to_latest_treatment_when_none_arrived(): void
    {
        $patient = Patients::factory()->create();
        $oldCentre = Locations::factory()->create(['name' => 'Old Centre']);
        $latestCentre = Locations::factory()->create(['name' => 'Latest Centre']);

        Appointments::factory()->create([
            'patient_id' => $patient->id,
            'appointment_type_id' => $this->treatmentTypeId,
            'appointment_status_id' => $this->bookedStatusId,
            'location_id' => $oldCentre->id,
            'scheduled_date' => '2026-01-05',
        ]);
        Appointments::factory()->create([
            'patient_id' => $patient->id,
            'appointment_type_id' => $this->treatmentTypeId,
            'appointment_status_id' => $this->cancelledStatusId, // still not arrived
            'location_id' => $latestCentre->id,
            'scheduled_date' => '2026-04-01',
        ]);

        $result = $this->service->treatmentLaunchLocation((int) $patient->id);

        $this->assertNotNull($result);
        $this->assertSame((int) $latestCentre->id, $result['location_id']);
    }

    public function test_ignores_consultation_appointments(): void
    {
        $patient = Patients::factory()->create();
        $treatmentCentre = Locations::factory()->create(['name' => 'Treatment Centre']);
        $consultCentre = Locations::factory()->create(['name' => 'Consult Centre']);

        // A treatment (older).
        Appointments::factory()->create([
            'patient_id' => $patient->id,
            'appointment_type_id' => $this->treatmentTypeId,
            'appointment_status_id' => $this->bookedStatusId,
            'location_id' => $treatmentCentre->id,
            'scheduled_date' => '2026-02-01',
        ]);
        // A newer CONSULTATION — must be ignored by the treatment launcher.
        Appointments::factory()->create([
            'patient_id' => $patient->id,
            'appointment_type_id' => $this->consultancyTypeId,
            'appointment_status_id' => $this->arrivedStatusId,
            'location_id' => $consultCentre->id,
            'scheduled_date' => '2026-05-01',
        ]);

        $result = $this->service->treatmentLaunchLocation((int) $patient->id);

        $this->assertNotNull($result);
        $this->assertSame((int) $treatmentCentre->id, $result['location_id']);
    }

    public function test_returns_null_when_patient_has_no_treatment(): void
    {
        $patient = Patients::factory()->create();

        $this->assertNull(
            $this->service->treatmentLaunchLocation((int) $patient->id),
        );
    }
}
