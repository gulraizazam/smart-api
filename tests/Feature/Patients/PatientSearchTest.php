<?php

declare(strict_types=1);

namespace Tests\Feature\Patients;

use App\Models\Appointments;
use App\Models\Locations;
use App\Models\Patients;
use App\Models\User;
use App\Services\PatientManagement\PatientService;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * PatientService::searchPatients pins three discriminated paths the
 * audit walked through and that are easy to break in refactors:
 *
 *   1. Numeric exact id — `searchPatients("42")` should resolve to the
 *      patient with id 42 if one exists in this account, regardless
 *      of any name LIKE matches the digit "42" might fish out of the
 *      catalogue. The "exact id wins" semantics keeps `C-42` in the
 *      typeahead from getting buried by 42 unrelated patients whose
 *      phone numbers happen to contain "42".
 *
 *   2. Numeric LIKE fallback — when no exact id matches, the search
 *      walks `id LIKE %n%` OR `phone LIKE %n%` so partial id and
 *      partial phone both hit. The audit added the phone-LIKE branch
 *      after a complaint that "0312" matched no one.
 *
 *   3. Name search — non-numeric input falls through to a `name LIKE`
 *      query, capped at 20 rows. The cap is the back-pressure on the
 *      typeahead — without it, a single-character search would hand
 *      back the entire patient list.
 *
 * Tenant boundary: every branch is scoped by account_id, so a search
 * MUST NOT return rows from another tenant even if the id collides.
 */
class PatientSearchTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private PatientService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
        $this->service = app(PatientService::class);
    }

    public function test_search_by_exact_id_returns_only_that_patient(): void
    {
        $alice = Patients::factory()->create(['name' => 'Alice Exact', 'phone' => '3001110000']);
        Patients::factory()->create(['name' => 'Bob Other', 'phone' => '3009990000']);

        $results = $this->service->searchPatients((string) $alice->id, accountId: 1);

        $this->assertCount(
            1,
            $results,
            'Exact-id search must short-circuit to a single result, even if other rows would also LIKE-match.'
        );
        $this->assertSame($alice->id, (int) $results[0]['id']);
        $this->assertSame('Alice Exact', $results[0]['name']);
    }

    public function test_search_by_name_returns_only_matching_rows(): void
    {
        Patients::factory()->create(['name' => 'Charlotte Test', 'phone' => '3007777777']);
        Patients::factory()->create(['name' => 'David Test', 'phone' => '3008888888']);
        Patients::factory()->create(['name' => 'Unrelated Person', 'phone' => '3009999999']);

        $results = $this->service->searchPatients('Test', accountId: 1);

        $names = array_column($results, 'name');
        $this->assertContains('Charlotte Test', $names);
        $this->assertContains('David Test', $names);
        $this->assertNotContains('Unrelated Person', $names);
    }

    public function test_search_by_partial_phone_uses_phone_like_branch(): void
    {
        // The audit added the phone-LIKE branch after a complaint that
        // partial-phone searches returned nothing. Pin it: a substring
        // of a stored phone must match.
        Patients::factory()->create(['name' => 'Phone Match', 'phone' => '3001234567']);
        Patients::factory()->create(['name' => 'Phone Other', 'phone' => '3009990000']);

        $results = $this->service->searchPatients('1234567', accountId: 1);

        $names = array_column($results, 'name');
        $this->assertContains('Phone Match', $names);
        $this->assertNotContains('Phone Other', $names);
    }

    public function test_search_caps_results_at_twenty(): void
    {
        // The query has ->limit(20) — pin it. Without the cap a single
        // character could return the whole patient table.
        Patients::factory()->count(25)->create(['name' => 'CapTest Person']);

        $results = $this->service->searchPatients('CapTest', accountId: 1);

        $this->assertLessThanOrEqual(
            20,
            count($results),
            'searchPatients must cap result count at 20 to keep the typeahead responsive.'
        );
    }

    public function test_search_excludes_inactive_patients(): void
    {
        Patients::factory()->create(['name' => 'Active Hit', 'active' => 1]);
        Patients::factory()->create(['name' => 'Active Miss', 'active' => 0]);

        $results = $this->service->searchPatients('Active', accountId: 1);

        $names = array_column($results, 'name');
        $this->assertContains('Active Hit', $names);
        $this->assertNotContains(
            'Active Miss',
            $names,
            'Inactive patients must be filtered out of the typeahead — the search clause '
            . 'pins ->where(\'active\', 1).'
        );
    }

    public function test_search_is_scoped_to_the_passed_account_id(): void
    {
        // The accountId argument is the tenant boundary — searching with
        // the wrong account must NOT leak rows from another tenant.
        // Create two patients in account 1, then search account 999;
        // expect zero results regardless of name match.
        Patients::factory()->create(['name' => 'Tenant Leak Test', 'account_id' => 1]);

        $results = $this->service->searchPatients('Tenant Leak', accountId: 999);

        $this->assertSame(
            0,
            count($results),
            'searchPatients must be scoped to the explicitly passed account_id.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Location-scoping regression tests
    |--------------------------------------------------------------------------
    |
    | The typeahead feeds consultation/treatment/patient/plan flows. A non
    | super-admin caller must only see patients who have at least one
    | appointment at a centre the caller is assigned to. Holding an
    | "All Centres" virtual centre flips the scope to unrestricted.
    |
    | These pin the contract so a refactor can't silently drop
    | PatientAccessScope::applyTo() from PatientService::searchPatients().
    */

    public function test_search_hides_patients_with_appointments_only_at_unassigned_centres(): void
    {
        $assignedCentre = Locations::factory()->create(['name' => 'Assigned Centre']);
        $otherCentre = Locations::factory()->create(['name' => 'Other Centre']);

        $visible = Patients::factory()->create(['name' => 'Visible Patient']);
        $hidden = Patients::factory()->create(['name' => 'Hidden Patient']);

        Appointments::factory()->create([
            'patient_id' => $visible->id,
            'location_id' => $assignedCentre->id,
        ]);
        Appointments::factory()->create([
            'patient_id' => $hidden->id,
            'location_id' => $otherCentre->id,
        ]);

        $this->actingAsScopedUser([$assignedCentre->id]);

        $results = $this->service->searchPatients('Patient', accountId: 1);

        $names = array_column($results, 'name');
        $this->assertContains('Visible Patient', $names);
        $this->assertNotContains(
            'Hidden Patient',
            $names,
            'A caller assigned only to Centre A must not see a patient whose appointments '
            . 'all sit at Centre B. PatientAccessScope::applyTo() enforces this on the '
            . 'shared typeahead used by consultation/treatment/patient/plan flows.'
        );
    }

    public function test_search_by_exact_id_still_respects_location_scope(): void
    {
        // Exact-id lookup is the easiest path to accidentally bypass the
        // scope — it short-circuits to a single-row SELECT. Pin that the
        // scope still applies to the clone.
        $assignedCentre = Locations::factory()->create(['name' => 'My Centre']);
        $otherCentre = Locations::factory()->create(['name' => 'Their Centre']);

        $hidden = Patients::factory()->create(['name' => 'Cross-Centre Patient']);
        Appointments::factory()->create([
            'patient_id' => $hidden->id,
            'location_id' => $otherCentre->id,
        ]);

        $this->actingAsScopedUser([$assignedCentre->id]);

        $results = $this->service->searchPatients((string) $hidden->id, accountId: 1);

        $this->assertSame(
            0,
            count($results),
            'Exact-id lookup must inherit the location scope from the base query — '
            . 'otherwise a scoped user could enumerate patients by typing ids.'
        );
    }

    public function test_search_with_all_centres_virtual_assignment_is_unrestricted(): void
    {
        // Holding an "All Centres" (or "All South Region" / "All Central
        // Region") virtual centre in user_has_locations flips the scope to
        // null — the caller sees every patient in the account, even
        // patients without any appointment at all.
        $allCentres = Locations::factory()->create(['name' => 'All Centres']);
        $regularCentre = Locations::factory()->create(['name' => 'Regular Centre']);

        // An appointment-less patient is the key case: the EXISTS-on-
        // appointments scope would hide them, so their visibility proves
        // the scope was bypassed entirely.
        Patients::factory()->create(['name' => 'Orphan Patient']);

        $atRegular = Patients::factory()->create(['name' => 'Regular Patient']);
        Appointments::factory()->create([
            'patient_id' => $atRegular->id,
            'location_id' => $regularCentre->id,
        ]);

        $this->actingAsScopedUser([$allCentres->id]);

        $results = $this->service->searchPatients('Patient', accountId: 1);

        $names = array_column($results, 'name');
        $this->assertContains(
            'Orphan Patient',
            $names,
            '"All Centres" assignment must flip PatientAccessScope to unrestricted — '
            . 'even a patient with zero appointments should appear.'
        );
        $this->assertContains('Regular Patient', $names);
    }

    public function test_search_with_zero_location_assignments_returns_nothing(): void
    {
        // A non super-admin caller with no user_has_locations rows has
        // empty scope — the helper emits `1=0` and the typeahead must
        // return zero regardless of what patients exist.
        $centre = Locations::factory()->create(['name' => 'Some Centre']);
        $patient = Patients::factory()->create(['name' => 'Any Patient']);
        Appointments::factory()->create([
            'patient_id' => $patient->id,
            'location_id' => $centre->id,
        ]);

        $this->actingAsScopedUser([]);

        $this->assertSame(
            [],
            $this->service->searchPatients('Patient', accountId: 1),
            'A caller with zero assigned locations must see zero patients — '
            . 'no silent full-DB leak when user_has_locations is empty.'
        );
    }

    /**
     * Create and authenticate an ordinary application user with an
     * explicit `user_has_locations` assignment set. Bypasses the
     * super-admin (id=1) short-circuit so the scope actually runs.
     *
     * @param  int[]  $locationIds
     */
    private function actingAsScopedUser(array $locationIds): User
    {
        // Ensure the acting user is NOT id=1 — PatientAccessScope::resolve()
        // returns null (unrestricted) for the super-admin, which would
        // defeat every scope assertion below.
        if (User::query()->count() === 0) {
            User::factory()->admin()->create();
        }

        $user = User::factory()->create(['user_type_id' => 1]);

        foreach ($locationIds as $locationId) {
            DB::table('user_has_locations')->insert([
                'user_id' => $user->id,
                'location_id' => $locationId,
                'region_id' => 1,
            ]);
        }

        $this->actingAs($user);

        return $user;
    }

    public function test_search_returns_only_id_name_phone_columns(): void
    {
        // The audit pinned the projection to `name, id, phone` for the
        // typeahead so that sensitive columns (cnic, dob, address)
        // never leak through the search response. Pin it.
        Patients::factory()->create([
            'name' => 'Projection Test',
            'phone' => '3001112233',
            'cnic' => '12345-1234567-1',
            'dob' => '1990-01-01',
        ]);

        $results = $this->service->searchPatients('Projection', accountId: 1);

        $this->assertNotEmpty($results);
        $row = $results[0];
        $this->assertArrayHasKey('id', $row);
        $this->assertArrayHasKey('name', $row);
        $this->assertArrayHasKey('phone', $row);
        $this->assertArrayNotHasKey(
            'cnic',
            $row,
            'searchPatients projection must NOT include encrypted PII (cnic).'
        );
        $this->assertArrayNotHasKey(
            'dob',
            $row,
            'searchPatients projection must NOT include date of birth.'
        );
    }
}
