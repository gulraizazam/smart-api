<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Patients;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Smoke coverage for the optimised patient API surface
 * (App\Http\Controllers\Api\PatientController). These routes live
 * behind `auth.common`, so unauthenticated traffic redirects / 401s
 * and authenticated callers hit the Api success/error envelope.
 *
 * Pins:
 *   1. Unauthenticated calls to `patients/search` do not leak data.
 *   2. Search on an empty DB returns a 200 envelope with an empty
 *      `data.patients` array (not a 500, and not a missing key).
 *   3. Search by id returns the seeded patient in the expected shape.
 */
class ApiPatientTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
    }

    /**
     * Patients inserts bypass the Patients model's global scope by going
     * through the raw users table. `user_type_id = 3` is the patient
     * identity row and `active = 1` is the searchPatients() prefilter.
     */
    private function seedPatient(string $name, string $phone): int
    {
        return (int) DB::table('users')->insertGetId([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)) . '@smoke.test',
            'password' => bcrypt('irrelevant'),
            'phone' => $phone,
            'gender' => 0,
            'user_type_id' => 3,
            'account_id' => 1,
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_unauthenticated_search_does_not_leak_data(): void
    {
        $response = $this->getJson('/api/patients/search?search=foo');

        $this->assertNotSame(200, $response->status());
        $this->assertContains($response->status(), [302, 401, 403, 419]);
    }

    public function test_authenticated_search_on_empty_db_returns_envelope_with_empty_list(): void
    {
        $this->actingAsAdmin();

        $response = $this->getJson('/api/patients/search?search=doesnotexist');

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => ['patients'],
        ]);
        $this->assertSame([], $response->json('data.patients'));
    }

    public function test_authenticated_search_by_numeric_id_returns_seeded_patient(): void
    {
        $this->actingAsAdmin();

        $patientId = $this->seedPatient('Search Smoke', '+923001234567');

        $response = $this->getJson('/api/patients/search?search=' . $patientId);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $patients = $response->json('data.patients');
        $this->assertIsArray($patients);
        $this->assertNotEmpty($patients);

        $ids = array_column($patients, 'id');
        $this->assertContains($patientId, $ids);
    }

    public function test_authenticated_search_by_name_returns_matching_patient(): void
    {
        $this->actingAsAdmin();

        $this->seedPatient('Aisha Fatima Khan', '03001234567');

        $response = $this->getJson('/api/patients/search?search=Aisha');

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $patients = $response->json('data.patients');
        $this->assertNotEmpty($patients, 'Search by name must return the seeded patient.');

        $names = array_column($patients, 'name');
        $this->assertTrue(
            collect($names)->contains(fn ($n) => str_contains($n, 'Aisha')),
            'The returned patient must contain the search term in the name.',
        );
    }
}
