<?php

declare(strict_types=1);

namespace Tests\Feature\Plan;

use App\Models\PackageAdvances;
use App\Models\Packages;
use App\Models\Patients;
use App\Models\PaymentModes;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * "Edit-payment" attack vectors — equivalent of the create-side
 * FakePaymentAttackVectorsTest but for:
 *
 *   - GET  /api/packages/edit_cash/{id}/{package_id}   (read for edit)
 *   - PUT  /api/packages/edit_cash/store                 (commit edit)
 *   - POST /api/packages/delete/cash                     (delete payment)
 *
 * Pre-2026-05-15 these endpoints had ZERO permission checks, ZERO input
 * validation, and ZERO tenant scope on lookups. Any authenticated
 * user could:
 *   - read any tenant's payment (IDOR via id guessing)
 *   - quietly edit an existing payment to lower the recorded amount
 *     and pocket the cash
 *   - delete a payment they recorded themselves (no SoD)
 *
 * Each test below pins the corresponding defence. If any goes red, a
 * money-stealing primitive on the edit path has been reopened.
 */
class EditPaymentAttackVectorsTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private Patients $patient;

    private Packages $package;

    private PaymentModes $cash;

    private PackageAdvances $existingAdvance;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
        $this->cash = PaymentModes::query()->where('name', 'Cash')->firstOrFail();
        $this->patient = Patients::factory()->create();
        $this->package = Packages::factory()->create([
            'patient_id' => $this->patient->id,
            'location_id' => $this->defaultLocation->id,
            'total_price' => 10000,
        ]);
        $this->existingAdvance = PackageAdvances::factory()->create([
            'cash_flow' => 'in',
            'cash_amount' => 5000,
            'patient_id' => $this->patient->id,
            'package_id' => $this->package->id,
            'location_id' => $this->defaultLocation->id,
            'payment_mode_id' => $this->cash->id,
        ]);
    }

    /* -------- GET read-for-edit -------- */

    public function test_get_edit_payment_rejects_unauthenticated_request(): void
    {
        Auth::logout();

        $response = $this->getJson("/api/packages/edit_cash/{$this->existingAdvance->id}/{$this->package->id}");

        $this->assertContains($response->status(), [401, 403],
            'Unauthenticated GET on the read-for-edit endpoint must be rejected.');
    }

    public function test_get_edit_payment_rejects_user_without_finances_edit(): void
    {
        $user = User::factory()->create(['account_id' => 1]);
        $this->actingAs($user);

        $response = $this->getJson("/api/packages/edit_cash/{$this->existingAdvance->id}/{$this->package->id}");

        $response->assertStatus(403);
    }

    public function test_get_edit_payment_rejects_cross_tenant_advance(): void
    {
        $tenantBIds = $this->seedTenantB();

        $response = $this->getJson(
            "/api/packages/edit_cash/{$tenantBIds['advance_id']}/{$tenantBIds['package_id']}",
        );

        $response->assertStatus(404);
        $this->assertNotEquals(200, $response->status(),
            'Cross-tenant advance fetch must NOT return data — even 403 leaks existence; 404 is correct.');
    }

    /* -------- PUT save edit -------- */

    public function test_put_edit_payment_rejects_user_without_finances_edit(): void
    {
        $user = User::factory()->create(['account_id' => 1]);
        $this->actingAs($user);

        $response = $this->putJson('/api/packages/edit_cash/store', [
            'package_advances_id' => $this->existingAdvance->id,
            'package_id' => $this->package->id,
            'payment_mode_id' => $this->cash->id,
            'cash_amount' => 100,
            'created_at' => now()->format('Y-m-d'),
        ]);

        $response->assertStatus(403);
        $this->assertSame(5000.0, (float) $this->existingAdvance->fresh()->cash_amount,
            'No-permission attempt must NOT mutate the advance.');
    }

    public function test_put_edit_payment_rejects_cross_tenant_advance(): void
    {
        $tenantBIds = $this->seedTenantB();

        $response = $this->putJson('/api/packages/edit_cash/store', [
            'package_advances_id' => $tenantBIds['advance_id'],
            'package_id' => $this->package->id,
            'payment_mode_id' => $this->cash->id,
            'cash_amount' => 100,
            'created_at' => now()->format('Y-m-d'),
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('package_advances_id', $response->json('errors', []));
    }

    public function test_put_edit_payment_rejects_negative_amount(): void
    {
        $response = $this->putJson('/api/packages/edit_cash/store', [
            'package_advances_id' => $this->existingAdvance->id,
            'package_id' => $this->package->id,
            'payment_mode_id' => $this->cash->id,
            'cash_amount' => -100,
            'created_at' => now()->format('Y-m-d'),
        ]);

        $response->assertStatus(422);
    }

    public function test_put_edit_payment_rejects_zero_amount(): void
    {
        $response = $this->putJson('/api/packages/edit_cash/store', [
            'package_advances_id' => $this->existingAdvance->id,
            'package_id' => $this->package->id,
            'payment_mode_id' => $this->cash->id,
            'cash_amount' => 0,
            'created_at' => now()->format('Y-m-d'),
        ]);

        $response->assertStatus(422);
    }

    public function test_put_edit_payment_rejects_future_dated_created_at(): void
    {
        $response = $this->putJson('/api/packages/edit_cash/store', [
            'package_advances_id' => $this->existingAdvance->id,
            'package_id' => $this->package->id,
            'payment_mode_id' => $this->cash->id,
            'cash_amount' => 1000,
            'created_at' => now()->addYear()->format('Y-m-d'),
        ]);

        $response->assertStatus(422);
    }

    public function test_put_edit_payment_rejects_cross_tenant_payment_mode(): void
    {
        $tenantBIds = $this->seedTenantB();

        $response = $this->putJson('/api/packages/edit_cash/store', [
            'package_advances_id' => $this->existingAdvance->id,
            'package_id' => $this->package->id,
            'payment_mode_id' => $tenantBIds['payment_mode_id'],
            'cash_amount' => 1000,
            'created_at' => now()->format('Y-m-d'),
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('payment_mode_id', $response->json('errors', []));
    }

    /* -------- DELETE -------- */

    public function test_delete_payment_rejects_user_without_finances_manage(): void
    {
        $user = User::factory()->create(['account_id' => 1]);
        $this->actingAs($user);

        $response = $this->postJson('/api/packages/delete/cash', [
            'package_advance_id' => $this->existingAdvance->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_delete_payment_rejects_creator_self_delete(): void
    {
        $creator = $this->actingAsAdmin();
        $advance = PackageAdvances::factory()->create([
            'cash_flow' => 'in',
            'cash_amount' => 100,
            'patient_id' => $this->patient->id,
            'package_id' => $this->package->id,
            'location_id' => $this->defaultLocation->id,
            'payment_mode_id' => $this->cash->id,
            'created_by' => $creator->id,
        ]);

        $response = $this->postJson('/api/packages/delete/cash', [
            'package_advance_id' => $advance->id,
        ]);

        $response->assertStatus(403);
        $this->assertStringContainsString(
            'cannot delete a payment you created',
            strtolower((string) $response->json('message')),
            'Creator-self-delete must be blocked by separation-of-duties.',
        );
    }

    public function test_delete_payment_rejects_cross_tenant_id(): void
    {
        $tenantBIds = $this->seedTenantB();

        $response = $this->postJson('/api/packages/delete/cash', [
            'package_advance_id' => $tenantBIds['advance_id'],
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('package_advance_id', $response->json('errors', []));
    }

    /* -------- overpayment is allowed (no cap, by product rule) -------- */

    /**
     * Product rule: an operator MAY record a payment larger than the
     * outstanding balance (advance / credit — outstanding can go
     * negative). The server must NOT reintroduce an over-payment cap:
     * a payment exceeding outstanding must not be rejected with the
     * "exceed the outstanding balance" 422 that briefly existed.
     */
    public function test_update_membership_plan_does_not_cap_overpayment(): void
    {
        $response = $this->postJson('/api/packages/update_membership_plan', [
            'package_id' => $this->package->id,
            'patient_id' => $this->patient->id,
            'location_id' => $this->defaultLocation->id,
            'payment_mode_id' => $this->cash->id,
            'cash_amount' => 6000,        // + 5000 already paid > 10000 total
            'grand_total' => 10000,
        ]);

        // Downstream membership wiring may still fail for this minimal
        // fixture, but the over-payment guard specifically must be gone.
        $this->assertNotSame(
            422,
            $response->status(),
            'Overpayment must not be capped — the 422 balance guard was removed by product decision.',
        );
        $this->assertStringNotContainsString(
            'exceed the outstanding balance',
            (string) $response->json('message'),
        );
    }

    /* --------------------------------------------------------------- */

    /**
     * @return array{patient_id:int, package_id:int, payment_mode_id:int, advance_id:int}
     */
    private function seedTenantB(): array
    {
        DB::table('accounts')->insertOrIgnore([
            'id' => 2,
            'name' => 'Tenant B',
            'email' => 'b@x.test',
            'contact' => '0',
            'suspended' => '0',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $locId = DB::table('locations')->insertGetId([
            'account_id' => 2, 'name' => 'B-loc', 'active' => 1,
            'city_id' => 1, 'region_id' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $patientId = DB::table('users')->insertGetId([
            'account_id' => 2, 'name' => 'B-patient',
            'email' => 'bp+'.uniqid().'@x.test', 'password' => bcrypt('x'),
            'user_type_id' => 3, 'active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $packageId = DB::table('packages')->insertGetId([
            'random_id' => (string) Str::uuid(), 'account_id' => 2,
            'name' => 'B-plan', 'plan_name' => 'b', 'sessioncount' => 1,
            'total_price' => 1000, 'is_exclusive' => 0, 'plan_type' => 'plan',
            'patient_id' => $patientId, 'location_id' => $locId, 'active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $paymentModeId = DB::table('payment_modes')->insertGetId([
            'account_id' => 2, 'name' => 'B-Cash', 'active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $advanceId = DB::table('package_advances')->insertGetId([
            'account_id' => 2, 'cash_flow' => 'in', 'cash_amount' => 200,
            'patient_id' => $patientId, 'package_id' => $packageId,
            'payment_mode_id' => $paymentModeId, 'location_id' => $locId,
            'is_cancel' => 0, 'active' => 1, 'appointment_type_id' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return [
            'patient_id' => (int) $patientId,
            'package_id' => (int) $packageId,
            'payment_mode_id' => (int) $paymentModeId,
            'advance_id' => (int) $advanceId,
        ];
    }
}
