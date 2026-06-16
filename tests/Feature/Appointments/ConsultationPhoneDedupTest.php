<?php

declare(strict_types=1);

namespace Tests\Feature\Appointments;

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
 * A phone number maps to exactly ONE patient within an account. The SPA resolves
 * the patient_id client-side via an async phone lookup, but the Save button does
 * not wait for it — a fast submit, or any non-SPA caller (legacy crm2 / direct
 * API), can arrive with a phone and no patient_id. The create flow must then
 * look the phone up server-side and ATTACH to the existing patient instead of
 * minting a duplicate. The classic trigger is a repeat visit at a different
 * branch, where the operator types the phone fresh.
 */
class ConsultationPhoneDedupTest extends TestCase
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

    public function test_bare_phone_at_another_branch_reuses_the_existing_patient(): void
    {
        $branch2 = Locations::factory()->create();
        $doctor = User::factory()->doctor()->create();
        $service = Services::factory()->create();
        $this->allocateService($doctor->id, $branch2->id, $service->id);

        // Patient already exists in the account (originally registered via a
        // consultation at another branch).
        $patient = Patients::factory()->create(['phone' => '3055544333', 'account_id' => 1]);

        $patientsBefore = Patients::query()->count();

        // New consultation at a DIFFERENT branch, entered by phone with nothing
        // resolved client-side: no patient_id, no lead_id — the race / non-SPA case.
        $appt = app(ConsultancyService::class)->createConsultancy([
            'appointment_type_id' => $this->consultancyTypeId(),
            'appointment_status_id' => 1,
            'location_id' => $branch2->id,
            'doctor_id' => $doctor->id,
            'service_id' => $service->id,
            'phone' => '3055544333',
            'name' => 'Repeat Visitor',
            'gender' => 1,
        ]);

        $this->assertSame(
            $patient->id,
            (int) $appt->patient_id,
            'A consultation from a bare phone must attach to the EXISTING patient.',
        );
        $this->assertSame(
            $patientsBefore,
            Patients::query()->count(),
            'No duplicate patient may be created for an already-registered phone.',
        );
    }

    public function test_matches_even_when_the_stored_phone_kept_a_leading_zero(): void
    {
        $branch = Locations::factory()->create();
        $doctor = User::factory()->doctor()->create();
        $service = Services::factory()->create();
        $this->allocateService($doctor->id, $branch->id, $service->id);

        // Legacy/walk-in shape: the patient row stored the raw leading zero.
        $patient = Patients::factory()->create(['phone' => '03077165432', 'account_id' => 1]);

        $patientsBefore = Patients::query()->count();

        $appt = app(ConsultancyService::class)->createConsultancy([
            'appointment_type_id' => $this->consultancyTypeId(),
            'appointment_status_id' => 1,
            'location_id' => $branch->id,
            'doctor_id' => $doctor->id,
            'service_id' => $service->id,
            'phone' => '3077165432', // cleaned form, no leading zero
            'name' => 'Zero Patient',
            'gender' => 1,
        ]);

        $this->assertSame($patient->id, (int) $appt->patient_id);
        $this->assertSame($patientsBefore, Patients::query()->count(), 'No duplicate for a leading-zero stored phone.');
    }

    public function test_matches_even_when_the_stored_phone_is_formatted(): void
    {
        // The production duplicate: the patient's row (registered at Branch A)
        // kept FORMATTING — e.g. "0311 0088 221". The clean-variant whereIn on
        // the raw `phone` column missed it, so an FDM creating a consultation at
        // Branch B (entering the clean number) minted a DUPLICATE. The
        // phone_normalized match must now attach to the existing patient.
        $branchB = Locations::factory()->create();
        $doctor = User::factory()->doctor()->create();
        $service = Services::factory()->create();
        $this->allocateService($doctor->id, $branchB->id, $service->id);

        $patient = Patients::factory()->create(['phone' => '0311 0088 221', 'account_id' => 1]);
        $patientsBefore = Patients::query()->count();

        $appt = app(ConsultancyService::class)->createConsultancy([
            'appointment_type_id' => $this->consultancyTypeId(),
            'appointment_status_id' => 1,
            'location_id' => $branchB->id,
            'doctor_id' => $doctor->id,
            'service_id' => $service->id,
            'phone' => '03110088221', // entered clean at the second branch
            'name' => 'Repeat Visitor',
            'gender' => 1,
        ]);

        $this->assertSame(
            $patient->id,
            (int) $appt->patient_id,
            'A consultation must attach to the existing patient even when the stored phone is formatted.',
        );
        $this->assertSame(
            $patientsBefore,
            Patients::query()->count(),
            'No duplicate patient for a formatted, already-registered phone.',
        );
    }

    public function test_still_creates_a_patient_for_an_unregistered_phone(): void
    {
        // Guard must stay additive: a genuinely new phone still spawns a patient.
        $branch = Locations::factory()->create();
        $doctor = User::factory()->doctor()->create();
        $service = Services::factory()->create();
        $this->allocateService($doctor->id, $branch->id, $service->id);

        $patientsBefore = Patients::query()->count();

        $appt = app(ConsultancyService::class)->createConsultancy([
            'appointment_type_id' => $this->consultancyTypeId(),
            'appointment_status_id' => 1,
            'location_id' => $branch->id,
            'doctor_id' => $doctor->id,
            'service_id' => $service->id,
            'phone' => '3009990000', // not registered to anyone
            'name' => 'Brand New',
            'gender' => 1,
        ]);

        $this->assertSame(
            $patientsBefore + 1,
            Patients::query()->count(),
            'A new phone must still create exactly one patient.',
        );
        $this->assertNotNull($appt->patient_id);
    }
}
