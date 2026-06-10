<?php

declare(strict_types=1);

namespace Tests\Feature\Patients;

use App\Http\Resources\Consultancy\ConsultancyResource;
use App\Http\Resources\Lead\LeadResource;
use App\Http\Resources\Patient\PatientResource;
use App\Models\Appointments;
use App\Models\Leads;
use App\Models\Patients;
use App\Models\User;
use App\Services\PatientManagement\PatientService;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Pins the `contact` permission contract: when a user lacks the `contact`
 * permission, the phone attribute must be ABSENT from API/search output
 * (the key missing), not masked with `***********`, not blanked. A prior
 * iteration of this module returned `phone: "***********"` which both
 * leaked existence information and broke clients that dispatched on
 * `phone.length`.
 *
 * The symmetric case is asserted too — with the permission granted, the
 * phone value round-trips unchanged.
 */
class PatientContactPermissionTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private PatientService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->service = app(PatientService::class);
    }

    public function test_patient_resource_omits_phone_key_when_permission_denied(): void
    {
        $this->actingAsUserWithoutContact();
        $patient = Patients::factory()->create(['phone' => '3009876543']);

        // resolve() is what Laravel calls during response serialization — it
        // runs ConditionallyLoadsAttributes::filter() over the result of
        // toArray() and strips MissingValue entries. The raw toArray() leaves
        // them in place, so a test that asserts "phone key absent" must go
        // through resolve() to exercise the same code path the API does.
        $payload = (new PatientResource($patient))->resolve(new Request);

        $this->assertArrayNotHasKey(
            'phone',
            $payload,
            'PatientResource must drop the phone key entirely when the caller lacks the `contact` permission — not mask, not blank.'
        );
    }

    public function test_patient_resource_includes_phone_when_permission_granted(): void
    {
        $this->actingAsUserWithContact();
        $patient = Patients::factory()->create(['phone' => '3001234567']);

        $payload = (new PatientResource($patient))->resolve(new Request);

        $this->assertArrayHasKey('phone', $payload);
        $this->assertSame('3001234567', $payload['phone']);
    }

    public function test_lead_resource_omits_phone_key_when_permission_denied(): void
    {
        $this->actingAsUserWithoutContact();
        $patient = Patients::factory()->create(['phone' => '3005550000']);
        $lead = Leads::factory()->create([
            'patient_id' => $patient->id,
            'phone' => '3005550000',
        ]);

        $payload = (new LeadResource($lead))->resolve(new Request);

        $this->assertArrayNotHasKey('phone', $payload);
    }

    public function test_lead_resource_includes_phone_when_contact_granted(): void
    {
        // Regression: the broad `contact` perm must reveal the LEAD phone, not
        // just the patient phone. crm3 gates LeadResource on
        // `leads.list.view_contact`; without the alias bridge a `contact` grant
        // left the lead phone hidden even though crm2 shows it under `contact`.
        $this->actingAsUserWithContact();
        $patient = Patients::factory()->create(['phone' => '3005551234']);
        $lead = Leads::factory()->create([
            'patient_id' => $patient->id,
            'phone' => '3005551234',
        ]);

        $payload = (new LeadResource($lead))->resolve(new Request);

        $this->assertArrayHasKey('phone', $payload);
        $this->assertSame('3005551234', $payload['phone']);
    }

    public function test_consultancy_resource_includes_phone_when_contact_granted(): void
    {
        // Same contract as leads: the broad `contact` perm must reveal the
        // consultation phone. ConsultancyResource gates on
        // `consultations.list.view_contact`; the alias bridge is what lets a
        // `contact` grant satisfy it.
        $this->actingAsUserWithContact();
        $patient = Patients::factory()->create(['phone' => '3007778888']);
        $appointment = Appointments::factory()->create(['patient_id' => $patient->id]);

        $payload = (new ConsultancyResource($appointment->load('patient')))->toArray(new Request);

        $this->assertSame('3007778888', $payload['phone']);
    }

    public function test_consultancy_resource_omits_phone_when_permission_denied(): void
    {
        $this->actingAsUserWithoutContact();
        $patient = Patients::factory()->create(['phone' => '3002223333']);
        $appointment = Appointments::factory()->create(['patient_id' => $patient->id]);

        $payload = (new ConsultancyResource($appointment->load('patient')))->resolve(new Request);

        $this->assertArrayNotHasKey('phone', $payload);
    }

    public function test_search_patients_omits_phone_when_permission_denied(): void
    {
        $this->actingAsUserWithoutContact();
        Patients::factory()->create(['name' => 'Phantom Phone', 'phone' => '3331112222']);

        $results = $this->service->searchPatients('Phantom', accountId: 1);

        $this->assertNotEmpty($results, 'Search must still return rows — only the phone field is redacted.');
        foreach ($results as $row) {
            $this->assertArrayNotHasKey(
                'phone',
                $row,
                'searchPatients() must not ship a phone value (not even `***********`) to callers without the `contact` permission.'
            );
        }
    }

    public function test_get_patient_search_optimized_drops_phone_property_when_denied(): void
    {
        $this->actingAsUserWithoutContact();
        Patients::factory()->create(['name' => 'Silent Ring', 'phone' => '3009990000']);

        $rows = Patients::getPatientSearchOptimized('Silent', 1);

        $this->assertNotEmpty($rows);
        foreach ($rows as $row) {
            $this->assertFalse(
                property_exists($row, 'phone'),
                'getPatientSearchOptimized() must unset the phone property on denied callers; leaving it blank still shows a column in downstream renderers.'
            );
        }
    }

    public function test_get_patient_search_optimized_preserves_phone_when_granted(): void
    {
        $this->actingAsUserWithContact();
        Patients::factory()->create(['name' => 'Loud Ring', 'phone' => '3112223333']);

        $rows = Patients::getPatientSearchOptimized('Loud', 1);

        $this->assertNotEmpty($rows);
        $this->assertSame('3112223333', $rows[0]->phone);
    }

    public function test_get_patient_search_optimized_never_returns_cnic_email_or_dob(): void
    {
        // Security audit 2026-06: the picker SELECT used to return cnic/email/dob
        // to anyone who could search. The SPA only consumes id/name/phone.
        $this->actingAsUserWithContact();
        Patients::factory()->create([
            'name' => 'Cnicprobe Patient',
            'phone' => '3009998888',
            'cnic' => '11111-2222222-3',
            'email' => 'cnicprobe@example.test',
            'dob' => '1990-01-01',
        ]);

        $rows = Patients::getPatientSearchOptimized('Cnicprobe', 1);

        $this->assertNotEmpty($rows);
        foreach ($rows as $row) {
            $this->assertFalse(property_exists($row, 'cnic'), 'search must not return cnic');
            $this->assertFalse(property_exists($row, 'email'), 'search must not return email');
            $this->assertFalse(property_exists($row, 'dob'), 'search must not return dob');
        }
    }

    private function actingAsUserWithContact(): User
    {
        return $this->actingAsUserWithPermission(contactGranted: true);
    }

    private function actingAsUserWithoutContact(): User
    {
        return $this->actingAsUserWithPermission(contactGranted: false);
    }

    private function actingAsUserWithPermission(bool $contactGranted): User
    {
        $this->createPermission('contact');
        $role = $this->createRole($contactGranted ? 'ContactViewer' : 'ContactBlind');
        if ($contactGranted) {
            $role->givePermissionTo('contact');
        }

        $user = User::factory()->create(['account_id' => 1]);
        $this->assignRoleWithPivot($user, $role);

        // Give the user an "All Centres" virtual assignment so
        // PatientAccessScope doesn't pre-empt the search with 1=0. These
        // tests are about the `contact` permission specifically, not the
        // centre-scoping rule — without this the patient-access filter
        // empties every result before the permission check can run.
        $allCentres = \App\Models\Locations::factory()->create([
            'name' => 'All Centres',
            'account_id' => 1,
        ]);
        \Illuminate\Support\Facades\DB::table('user_has_locations')->insert([
            'user_id' => $user->id,
            'location_id' => $allCentres->id,
            'region_id' => 1, // seeded via seedRegionsAndCities()
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($user);

        return $user;
    }
}
