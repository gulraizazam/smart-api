<?php

declare(strict_types=1);

namespace Tests\Feature\Plan;

use App\Http\Controllers\Admin\PackageAdvancesController;
use App\Models\PackageAdvances;
use App\Models\Packages;
use App\Models\Patients;
use App\Models\PaymentModes;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * "Hacker adds a fake payment against a patient" — concrete proof of
 * defence. Each test simulates one attack vector and asserts the
 * write was blocked.
 *
 * Vectors covered:
 *   1. No credentials → 401 from auth middleware
 *   2. Valid credentials, no `finances_create` permission → 403
 *   3. Valid credentials for tenant A targeting tenant B's patient → 422
 *   4. Valid credentials for tenant A targeting tenant B's package → 422
 *   5. Valid credentials for tenant A targeting tenant B's payment_mode → 422
 *   6. Negative cash_amount (attacker tries to credit pool while
 *      pretending to record an income) → 422
 *   7. Zero cash_amount → 422
 *   8. Mass-assign attack: attacker injects `account_id`, `created_by`,
 *      `cash_flow` in the payload → defence-in-depth: server-set fields
 *      always overwrite the request (`GuardsTenantBoundary` trait
 *      + explicit controller assignment).
 *   9. Backdating attack: attacker injects `created_at` → ignored, the
 *      server timestamps the row.
 *  10. Replay / duplicate POST: same valid payload submitted twice DOES
 *      produce two rows — documenting the idempotency gap (open known).
 *
 * If any of these turn red, someone has opened a money-stealing
 * primitive on the plans surface. Treat as P0.
 */
class FakePaymentAttackVectorsTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private Patients $patient;

    private Packages $package;

    private PaymentModes $cash;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->cash = PaymentModes::query()->where('name', 'Cash')->firstOrFail();
    }

    /* ============================================================== */
    /* Vector 1 — no credentials                                      */
    /* ============================================================== */

    public function test_unauthenticated_request_is_rejected(): void
    {
        // Do NOT call actingAsAdmin — anonymous.
        $response = $this->postJson('/api/finances/savepackagesadvances', [
            'cash_amount' => 1000,
            'patient_id' => 1,
            'package_id' => 1,
            'payment_mode_id' => 1,
        ]);

        $this->assertContains(
            $response->status(),
            [401, 403, 419],
            'Anonymous payment writes must be rejected at the auth layer (401) — got '.$response->status(),
        );
        $this->assertSame(0, DB::table('package_advances')->count(),
            'No package_advance row may persist when the caller is anonymous.');
    }

    /* ============================================================== */
    /* Vector 2 — valid credentials, no finances_create permission    */
    /* ============================================================== */

    public function test_user_without_finances_create_is_rejected(): void
    {
        // Plain user with no Super-Admin role, no `finances_create`
        // permission. They authenticate but the gate denies.
        $user = User::factory()->create(['account_id' => 1]);
        $this->actingAs($user);

        $this->setupPlanForCurrentTenant();

        $request = Request::create('/finances/savepackagesadvances', 'POST', [
            'cash_amount' => 1000,
            'patient_id' => $this->patient->id,
            'package_id' => $this->package->id,
            'payment_mode_id' => $this->cash->id,
        ]);
        $request->setUserResolver(fn () => Auth::user());

        $response = app(PackageAdvancesController::class)->savepackagesadvances($request);

        $this->assertSame(403, $response->getStatusCode(),
            'A user without finances_create must get 403, never 200.');
        $this->assertSame(0, DB::table('package_advances')->count(),
            'No package_advance row may persist when the gate denies.');
    }

    /* ============================================================== */
    /* Vector 3 — cross-tenant patient injection                      */
    /* ============================================================== */

    public function test_cross_tenant_patient_id_is_rejected(): void
    {
        $this->actingAsAdmin();
        $this->setupPlanForCurrentTenant();
        $tenantBIds = $this->seedTenantB();

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->callControllerDirectly(
            cashAmount: 1000,
            patientId: $tenantBIds['patient_id'],
        );
    }

    /* ============================================================== */
    /* Vector 4 — cross-tenant package injection                      */
    /* ============================================================== */

    public function test_cross_tenant_package_id_is_rejected(): void
    {
        $this->actingAsAdmin();
        $this->setupPlanForCurrentTenant();
        $tenantBIds = $this->seedTenantB();

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->callControllerDirectly(
            cashAmount: 1000,
            packageId: $tenantBIds['package_id'],
        );
    }

    /* ============================================================== */
    /* Vector 5 — cross-tenant payment_mode injection                 */
    /* ============================================================== */

    public function test_cross_tenant_payment_mode_is_rejected(): void
    {
        $this->actingAsAdmin();
        $this->setupPlanForCurrentTenant();
        $tenantBIds = $this->seedTenantB();

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->callControllerDirectly(
            cashAmount: 1000,
            paymentModeId: $tenantBIds['payment_mode_id'],
        );
    }

    /* ============================================================== */
    /* Vector 6 — negative cash_amount                                */
    /* ============================================================== */

    public function test_negative_cash_amount_is_rejected(): void
    {
        $this->actingAsAdmin();
        $this->setupPlanForCurrentTenant();

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->callControllerDirectly(cashAmount: -5000);
    }

    /* ============================================================== */
    /* Vector 7 — zero cash_amount                                    */
    /* ============================================================== */

    public function test_zero_cash_amount_is_rejected(): void
    {
        $this->actingAsAdmin();
        $this->setupPlanForCurrentTenant();

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->callControllerDirectly(cashAmount: 0);
    }

    /* ============================================================== */
    /* Vector 8 — mass-assignment of server-controlled fields         */
    /* ============================================================== */

    public function test_mass_assigned_account_id_and_created_by_are_ignored(): void
    {
        $admin = $this->actingAsAdmin();
        $this->setupPlanForCurrentTenant();

        $request = Request::create('/finances/savepackagesadvances', 'POST', [
            'cash_amount' => 1500,
            'patient_id' => $this->patient->id,
            'package_id' => $this->package->id,
            'payment_mode_id' => $this->cash->id,
            // Attacker overrides:
            'account_id' => 99999,         // try to write to a fake tenant
            'created_by' => 99999,         // try to impersonate another admin
            'cash_flow' => 'out',          // try to flip ledger direction
            'is_cancel' => 1,              // try to pre-cancel
            'is_refund' => 1,              // try to mask as refund
        ]);
        $request->setUserResolver(fn () => Auth::user());

        app(PackageAdvancesController::class)->savepackagesadvances($request);

        $row = PackageAdvances::query()->latest('id')->first();
        $this->assertNotNull($row);
        $this->assertSame(1, (int) $row->account_id,
            'account_id must come from the server, not the request payload (defence in depth: GuardsTenantBoundary + controller-side assignment).');
        $this->assertSame((int) $admin->id, (int) $row->created_by,
            'created_by must come from Auth::id(), not the request payload.');
        $this->assertSame('in', $row->cash_flow,
            'cash_flow is server-controlled (`in` on save) — attacker cannot flip the ledger sign.');
        $this->assertSame(0, (int) $row->is_cancel,
            'is_cancel must default to 0; attacker can not pre-cancel a row at create.');
    }

    /* ============================================================== */
    /* Vector 9 — backdating attack                                   */
    /* ============================================================== */

    public function test_created_at_in_request_is_ignored(): void
    {
        $this->actingAsAdmin();
        $this->setupPlanForCurrentTenant();

        $request = Request::create('/finances/savepackagesadvances', 'POST', [
            'cash_amount' => 700,
            'patient_id' => $this->patient->id,
            'package_id' => $this->package->id,
            'payment_mode_id' => $this->cash->id,
            'created_at' => '2020-01-01 00:00:00',  // attacker tries to backdate
        ]);
        $request->setUserResolver(fn () => Auth::user());

        app(PackageAdvancesController::class)->savepackagesadvances($request);

        $row = PackageAdvances::query()->latest('id')->first();
        $this->assertNotNull($row);
        $this->assertNotSame('2020-01-01 00:00:00', $row->created_at->format('Y-m-d H:i:s'),
            'created_at must be set by the server, not the request payload.');
        $this->assertTrue($row->created_at->isToday(),
            'New payment rows should be timestamped now, not 2020.');
    }

    /* ============================================================== */
    /* Vector 10 — replay / duplicate POST                            */
    /* ============================================================== */

    public function test_duplicate_post_creates_two_rows_documenting_idempotency_gap(): void
    {
        // OPEN GAP — Idempotency-Key middleware is not implemented yet
        // (CLAUDE.md mandates it but the cashflow + plans surfaces
        // don't enforce it). A duplicate POST today produces two rows.
        // This test documents the gap so future work fixes it explicitly.
        $this->actingAsAdmin();
        $this->setupPlanForCurrentTenant();

        $this->callControllerDirectly(cashAmount: 2000);
        $this->callControllerDirectly(cashAmount: 2000);

        $rows = PackageAdvances::query()->where('package_id', $this->package->id)->get();
        $this->assertCount(2, $rows,
            'Today, a duplicate POST creates two payment rows. This is the documented idempotency gap; future work should fix it via an Idempotency-Key middleware.');
    }

    /* ============================================================== */

    private function setupPlanForCurrentTenant(): void
    {
        $this->patient = Patients::factory()->create();
        $this->package = Packages::factory()->create([
            'patient_id' => $this->patient->id,
            'location_id' => $this->defaultLocation->id,
            'total_price' => 10000,
        ]);
    }

    /**
     * @return array{patient_id:int, package_id:int, payment_mode_id:int}
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
            'account_id' => 2,
            'name' => 'B-loc',
            'active' => 1,
            'city_id' => 1,
            'region_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $patientId = DB::table('users')->insertGetId([
            'account_id' => 2,
            'name' => 'B-patient',
            'email' => 'bp+'.uniqid().'@x.test',
            'password' => bcrypt('x'),
            'user_type_id' => 3,
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $packageId = (int) DB::table('packages')->insertGetId([
            'random_id' => (string) Str::uuid(),
            'account_id' => 2,
            'name' => 'B-plan',
            'plan_name' => 'b',
            'sessioncount' => 1,
            'total_price' => 1000,
            'is_exclusive' => 0,
            'plan_type' => 'plan',
            'patient_id' => $patientId,
            'location_id' => $locId,
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $paymentModeId = (int) DB::table('payment_modes')->insertGetId([
            'account_id' => 2,
            'name' => 'B-Cash',
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return compact('patientId', 'packageId', 'paymentModeId') + [
            'patient_id' => $patientId,
            'package_id' => $packageId,
            'payment_mode_id' => $paymentModeId,
        ];
    }

    private function callControllerDirectly(
        float $cashAmount,
        ?int $patientId = null,
        ?int $packageId = null,
        ?int $paymentModeId = null,
    ): array {
        $request = Request::create('/finances/savepackagesadvances', 'POST', [
            'cash_amount' => $cashAmount,
            'patient_id' => $patientId ?? $this->patient->id,
            'package_id' => $packageId ?? $this->package->id,
            'payment_mode_id' => $paymentModeId ?? $this->cash->id,
        ]);
        $request->setUserResolver(fn () => Auth::user());

        $response = app(PackageAdvancesController::class)->savepackagesadvances($request);

        return json_decode($response->getContent(), true) ?? [];
    }
}
