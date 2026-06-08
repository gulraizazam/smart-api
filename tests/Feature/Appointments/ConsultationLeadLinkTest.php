<?php

declare(strict_types=1);

namespace Tests\Feature\Appointments;

use App\Models\AppointmentTypes;
use App\Models\Leads;
use App\Models\LeadStatuses;
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
 * When the consultation lookup resolves an existing LEAD that has no patient
 * yet, the create flow must link to THAT lead: create exactly one patient,
 * attach it to the lead, NOT spawn a duplicate lead, flip the lead to Booked,
 * and activate the leads_services row for the consultation's service. Also pins
 * that the phone-match guard does not drop a lead stored with a leading zero.
 */
class ConsultationLeadLinkTest extends TestCase
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

    /** Allocate a service to a doctor at a location (passes the create-flow service guard). */
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

    public function test_links_to_an_existing_no_patient_lead_without_duplicating(): void
    {
        $bookedStatus = LeadStatuses::factory()->create(['account_id' => 1, 'is_booked' => 1]);
        $location = Locations::factory()->create();
        $doctor = User::factory()->doctor()->create();
        $service = Services::factory()->create();

        $this->allocateService($doctor->id, $location->id, $service->id);

        $lead = Leads::factory()->create([
            'phone' => '3055544333',
            'name' => 'Existing Lead',
            'patient_id' => null,
            'account_id' => 1,
            'lead_status_id' => null,
        ]);

        $leadsBefore = Leads::query()->count();
        $patientsBefore = Patients::query()->count();

        $appt = app(ConsultancyService::class)->createConsultancy([
            'appointment_type_id' => $this->consultancyTypeId(),
            'appointment_status_id' => 1,
            'location_id' => $location->id,
            'doctor_id' => $doctor->id,
            'service_id' => $service->id,
            'lead_id' => $lead->id,
            'phone' => $lead->phone,
            'name' => $lead->name,
            'gender' => 1,
        ]);

        $this->assertSame($lead->id, (int) $appt->lead_id, 'Consultation must link to the existing lead.');
        $this->assertSame($leadsBefore, Leads::query()->count(), 'No duplicate lead may be created.');
        $this->assertSame($patientsBefore + 1, Patients::query()->count(), 'Exactly one patient is created.');

        $lead->refresh();
        $this->assertNotNull($lead->patient_id, 'The created patient is linked back onto the lead.');
        $this->assertSame((int) $appt->patient_id, (int) $lead->patient_id);
        $this->assertSame($bookedStatus->id, (int) $lead->lead_status_id, 'Lead is flipped to Booked on create.');

        $this->assertDatabaseHas('leads_services', [
            'lead_id' => $lead->id,
            'service_id' => $service->id,
            'status' => 1,
        ]);
    }

    public function test_phone_guard_keeps_a_lead_stored_with_a_leading_zero(): void
    {
        // Lead stored WITH the leading zero; the form submits the cleaned form.
        // The create-flow phone guard compares cleanNumber() both sides, which
        // already collapses the leading zero — so the lead_id must survive.
        $location = Locations::factory()->create();
        $doctor = User::factory()->doctor()->create();
        $service = Services::factory()->create();

        $this->allocateService($doctor->id, $location->id, $service->id);

        $lead = Leads::factory()->create([
            'phone' => '03077165432',
            'name' => 'Zero Lead',
            'patient_id' => null,
            'account_id' => 1,
        ]);

        $appt = app(ConsultancyService::class)->createConsultancy([
            'appointment_type_id' => $this->consultancyTypeId(),
            'appointment_status_id' => 1,
            'location_id' => $location->id,
            'doctor_id' => $doctor->id,
            'service_id' => $service->id,
            'lead_id' => $lead->id,
            'phone' => '3077165432', // cleaned form, no leading zero
            'name' => $lead->name,
            'gender' => 1,
        ]);

        $this->assertSame(
            $lead->id,
            (int) $appt->lead_id,
            'A leading-zero-stored lead must not be dropped by the phone-match guard.',
        );
    }
}
