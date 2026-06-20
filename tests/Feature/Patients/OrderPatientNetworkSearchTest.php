<?php

declare(strict_types=1);

namespace Tests\Feature\Patients;

use App\Models\Appointments;
use App\Models\Locations;
use App\Models\Patients;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Order patient search is NETWORK-WIDE (whole account), not centre-scoped.
 *
 * Every other patient typeahead is filtered by PatientAccessScope to patients
 * who have an appointment at one of the caller's assigned centres. A product
 * sale isn't bound to where the patient was first seen, so the order flow opts
 * out of that filter — but ONLY for order-creating roles (`order_create`), so
 * the cross-centre PII relaxation can't be triggered from any other surface.
 *
 * These tests pin both halves:
 *   1. the per-centre default still hides an out-of-branch patient (regression
 *      guard — flipping the default to network-wide must fail here), and
 *   2. the network bypass surfaces that patient, by name AND by phone, and is
 *      gated on `order_create`.
 */
class OrderPatientNetworkSearchTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
    }

    public function test_default_search_is_centre_scoped_but_network_search_finds_out_of_centre_patient(): void
    {
        [, $centreA] = $this->actingAsSingleCentreUser(withOrderCreate: true);
        [$patient] = $this->makeOutOfCentrePatient($centreA);

        // Default (centre-scoped): the patient was only ever seen at another
        // branch, so the caller scoped to centre A must not see them.
        $scoped = Patients::getPatientSearchOptimized('Networkonly', 1);
        $this->assertEmpty(
            $scoped,
            'Default patient search must stay centre-scoped — an out-of-branch patient must not leak.'
        );

        // Network-wide: the same query, account-wide, surfaces the patient.
        $network = Patients::getPatientSearchOptimized('Networkonly', 1, networkWide: true);
        $this->assertNotEmpty($network, 'Network-wide search must reach patients from any branch.');
        $this->assertSame($patient->id, (int) $network[0]->id);
    }

    public function test_network_search_finds_out_of_centre_patient_by_phone(): void
    {
        // The reported case: a front-desk user types a phone for a patient who
        // belongs to another centre and gets "No matches" under the default
        // scope. Network-wide resolves the phone account-wide.
        [, $centreA] = $this->actingAsSingleCentreUser(withOrderCreate: true);
        [$patient] = $this->makeOutOfCentrePatient($centreA);

        $this->assertEmpty(
            Patients::getPatientSearchOptimized('3214445555', 1),
            'Centre-scoped phone search must miss the out-of-branch patient.'
        );

        $network = Patients::getPatientSearchOptimized('3214445555', 1, networkWide: true);
        $this->assertNotEmpty($network, 'Network-wide phone search must find the patient.');
        $this->assertSame($patient->id, (int) $network[0]->id);
    }

    public function test_network_param_is_honoured_only_for_order_creating_roles(): void
    {
        // No order_create → the network flag is ignored, search stays scoped.
        [, $centreA] = $this->actingAsSingleCentreUser(withOrderCreate: false);
        $this->makeOutOfCentrePatient($centreA);

        $denied = $this->getJson('/api/users/getpatient-optimized?search=Networkonly&network=1');
        $denied->assertOk();
        $denied->assertJsonMissing(['name' => 'Networkonly Patient']);

        // order_create → the network flag bypasses the centre scope.
        [, $centreA2] = $this->actingAsSingleCentreUser(withOrderCreate: true);
        $this->makeOutOfCentrePatient($centreA2);

        $allowed = $this->getJson('/api/users/getpatient-optimized?search=Networkonly&network=1');
        $allowed->assertOk();
        $allowed->assertJsonFragment(['name' => 'Networkonly Patient']);
    }

    /**
     * @return array{0: User, 1: Locations}
     */
    private function actingAsSingleCentreUser(bool $withOrderCreate): array
    {
        $slugs = ['patients.list.view', 'patients.list.view_contact', 'order_create'];
        foreach ($slugs as $slug) {
            $this->createPermission($slug);
        }

        $granted = ['patients.list.view', 'patients.list.view_contact'];
        if ($withOrderCreate) {
            $granted[] = 'order_create';
        }

        $role = $this->createRole($withOrderCreate ? 'OrderDeskNetwork' : 'OrderDeskScoped');
        $role->syncPermissions($granted);

        // PatientAccessScope has a legacy "id === 1 = root, unrestricted"
        // escape. Burn id 1 so our operator is a normal (id > 1) user and the
        // per-centre scope actually applies.
        if (User::max('id') === null) {
            User::factory()->create(['account_id' => 1]);
        }

        $user = User::factory()->create(['account_id' => 1]);
        $this->assignRoleWithPivot($user, $role);

        // Bind the user to exactly one centre (NOT "All Centres") so
        // PatientAccessScope restricts to that centre's patients.
        $centreA = Locations::factory()->create(['name' => 'Centre A', 'account_id' => 1]);
        DB::table('user_has_locations')->insert([
            'user_id' => $user->id,
            'location_id' => $centreA->id,
            'region_id' => 1,
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($user);

        return [$user, $centreA];
    }

    /**
     * A patient whose only appointment is at a DIFFERENT centre than the
     * caller's — invisible under the per-centre scope, visible network-wide.
     *
     * @return array{0: Patients, 1: Locations}
     */
    private function makeOutOfCentrePatient(Locations $callerCentre): array
    {
        $centreB = Locations::factory()->create(['name' => 'Centre B', 'account_id' => 1]);
        $patient = Patients::factory()->create([
            'name' => 'Networkonly Patient',
            'phone' => '3214445555',
            'account_id' => 1,
        ]);
        Appointments::factory()->create([
            'patient_id' => $patient->id,
            'location_id' => $centreB->id,
            'account_id' => 1,
        ]);

        return [$patient, $centreB];
    }
}
