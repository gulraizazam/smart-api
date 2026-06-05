<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Http\Resources\Patient\PatientDetailResource;
use App\Models\Patients;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Security audit 2026-06 — patient data exposure.
 *
 * Pins the contracts that closed a CRITICAL leak: the legacy
 * `GET /api/users/get_patient_number` endpoint serialized the raw Patients
 * model (which shares the `users` table) to any authenticated caller for any
 * id — leaking the bcrypt password hash, remember_token, cnic, email and dob,
 * with no permission gate and no account scoping.
 *
 *   1. The Patients model hides password + remember_token in serialization.
 *   2. get_patient_number is account-scoped (no cross-tenant read).
 *   3. get_patient_number returns only minimal, non-sensitive fields.
 *   4. PatientDetailResource gates cnic behind the contact permission.
 *
 * Note on fixtures: GuardsTenantBoundary stamps account_id to the
 * authenticated user's account on create, so a genuinely "foreign" (account 2)
 * record must be created while logged OUT.
 */
class PatientDataExposureTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->seedSecondAccount();
    }

    public function test_patients_model_hides_password_and_remember_token(): void
    {
        // PatientFactory sets a real bcrypt password; remember_token added here.
        $patient = Patients::factory()->create([
            'account_id' => 1,
            'remember_token' => 'should-never-serialize',
        ]);

        $array = $patient->fresh()->toArray();

        $this->assertArrayNotHasKey('password', $array, 'Patients must hide the password hash.');
        $this->assertArrayNotHasKey('remember_token', $array, 'Patients must hide remember_token.');
    }

    public function test_get_patient_number_rejects_cross_account_patient(): void
    {
        // Created logged-out so the account_id=2 override is honoured.
        $foreign = Patients::factory()->create(['account_id' => 2]);

        $this->actingAs(User::factory()->create(['account_id' => 1]));

        $this->getJson('/api/users/get_patient_number?patient_id=' . $foreign->id)
            ->assertStatus(404);
    }

    public function test_get_patient_number_returns_minimal_payload_without_sensitive_fields(): void
    {
        $patient = Patients::factory()->create(['account_id' => 1]);

        $this->actingAs(User::factory()->create(['account_id' => 1]));

        $response = $this->getJson('/api/users/get_patient_number?patient_id=' . $patient->id)
            ->assertOk();

        $data = $response->json('data.patient');

        $this->assertNotNull($data, 'Expected a patient payload under data.patient.');
        $this->assertEqualsCanonicalizing(
            ['id', 'patient_code', 'name', 'phone'],
            array_keys($data),
            'get_patient_number must expose only id, patient_code, name, phone.'
        );

        foreach (['password', 'remember_token', 'cnic', 'email', 'dob'] as $leaked) {
            $this->assertArrayNotHasKey($leaked, $data, "get_patient_number must not leak {$leaked}.");
        }
    }

    public function test_patient_detail_resource_omits_cnic_without_contact_permission(): void
    {
        $patient = Patients::factory()->create(['account_id' => 1, 'cnic' => '12345-6789012-3']);

        $this->actingAs(User::factory()->create(['account_id' => 1]));

        $payload = (new PatientDetailResource($patient))->resolve(new Request);

        $this->assertArrayNotHasKey(
            'cnic',
            $payload,
            'PatientDetailResource must drop the cnic key when the caller lacks the contact permission.'
        );
    }

    public function test_patient_detail_resource_includes_cnic_with_contact_permission(): void
    {
        $patient = Patients::factory()->create(['account_id' => 1, 'cnic' => '12345-6789012-3']);

        $this->actingAsUserWithContactPermission();

        $payload = (new PatientDetailResource($patient))->resolve(new Request);

        $this->assertArrayHasKey('cnic', $payload);
        $this->assertSame('12345-6789012-3', $payload['cnic']);
    }

    private function actingAsUserWithContactPermission(): User
    {
        $this->createPermission('patients.list.view_contact');
        $role = $this->createRole('ContactViewer');
        $role->givePermissionTo('patients.list.view_contact');

        $user = User::factory()->create(['account_id' => 1]);
        $this->assignRoleWithPivot($user, $role);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($user);

        return $user;
    }

    private function seedSecondAccount(): void
    {
        DB::table('accounts')->updateOrInsert(
            ['id' => 2],
            [
                'name' => 'Second Account',
                'email' => 'second@example.com',
                'contact' => '0000000001',
                'suspended' => '0',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
