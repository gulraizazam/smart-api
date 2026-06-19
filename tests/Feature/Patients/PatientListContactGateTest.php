<?php

declare(strict_types=1);

namespace Tests\Feature\Patients;

use App\Models\User;
use App\Services\PatientManagement\PatientService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Pins the patient-LIST contact gate found in the 2026-06-19 QA (task #19).
 * The list (PatientService::getDatatableData) returns the raw Eloquent
 * collection — it never runs through PatientResource — so `phone` and `email`
 * were emitted to ANY holder of `patients.list.view`, making the
 * `patients.list.view_contact` toggle decorative on the list. (The resource
 * path is correctly gated + already tested in PatientContactPermissionTest;
 * this is the list path it skipped.)
 *
 * Teeth: remove the mask and the "without view_contact" case goes red (real
 * phone/email leak back).
 */
class PatientListContactGateTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private const PHONE = '3009998888';
    private const EMAIL = 'contact.list@example.test';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->seedPatient();
    }

    /** A patient (user_type_id = 3) in account 1, with phone + email. */
    private function seedPatient(): void
    {
        DB::table('users')->insert([
            'name' => 'List Contact Patient',
            'email' => self::EMAIL,
            'password' => bcrypt('irrelevant'),
            'phone' => self::PHONE,
            'gender' => 0,
            'user_type_id' => 3,
            'account_id' => 1,
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function actingInAccount1With(array $perms): void
    {
        foreach ($perms as $p) {
            $this->createPermission($p);
        }
        $role = $this->createRole('plist_'.uniqid());
        $role->givePermissionTo($perms);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::factory()->create(['account_id' => 1]);
        DB::table('users')->where('id', $user->id)->update(['account_id' => 1]);
        $user->refresh();
        $this->assignRoleWithPivot($user, $role);
        $this->actingAs($user);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function firstPatientRow(): object
    {
        $result = app(PatientService::class)->getDatatableData(Request::create('/', 'GET'));

        return collect($result['data'])->firstWhere('email', self::EMAIL)
            ?? collect($result['data'])->first();
    }

    public function test_patient_list_masks_phone_and_email_without_view_contact(): void
    {
        $this->createPermission('patients.list.view_contact'); // exists, but NOT granted
        $this->actingInAccount1With(['patients.list.view']);

        $row = $this->firstPatientRow();

        $this->assertNull($row->phone, 'phone must be hidden on the list without patients.list.view_contact');
        $this->assertNull($row->email, 'email must be hidden on the list without patients.list.view_contact');
    }

    public function test_patient_list_shows_phone_and_email_with_view_contact(): void
    {
        $this->actingInAccount1With(['patients.list.view', 'patients.list.view_contact']);

        $row = $this->firstPatientRow();

        $this->assertSame(self::PHONE, $row->phone, 'a contact holder must still see the phone');
        $this->assertSame(self::EMAIL, $row->email, 'a contact holder must still see the email');
    }
}
