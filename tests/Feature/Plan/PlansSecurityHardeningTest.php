<?php

declare(strict_types=1);

namespace Tests\Feature\Plan;

use App\Http\Controllers\Admin\PackageAdvancesController;
use App\Models\CashFlow\CashPool;
use App\Models\Locations;
use App\Models\PackageAdvances;
use App\Models\Packages;
use App\Models\Patients;
use App\Models\PaymentModes;
use App\Services\Plan\PlanRefundService;
use App\Services\Plan\PlanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Round-1+2 security hardening on the /plans surface
 * (PackageAdvancesController, PlanRefundService, PlanService, the
 * AppointmentInvoiceController invoice path).
 *
 *  - C2/C3: permission gates on save/update were missing
 *  - C5+H4: cross-tenant FK injection + soft-deleted FK acceptance
 *  - C7:    PlanRefundService accepted refund > total paid in
 *  - C8:    negative cash_amount was accepted
 *  - H1:    saveinvoice didn't scope appointment_id to account
 *  - H2:    cancel() had no creator≠canceller check (SoD)
 *  - H5:    bulk-delete silently dropped cross-tenant ids
 *
 * Each test exercises the specific defence — if any of them turn red,
 * a fix has regressed and someone has reopened a money-stealing
 * primitive on the plans surface.
 */
class PlansSecurityHardeningTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private Packages $package;

    private Patients $patient;

    private PaymentModes $cash;

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
    }

    /* -------- C8 — negative amount rejected -------- */

    public function test_save_rejects_a_negative_cash_amount(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->callSave(-1000);
    }

    public function test_save_rejects_zero_cash_amount(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->callSave(0);
    }

    /* -------- C5+H4 — cross-tenant + soft-delete FK rejection -------- */

    public function test_save_rejects_a_package_from_another_tenant(): void
    {
        $tenantBPackageId = $this->seedTenantBPackage();

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->callSave(
            cashAmount: 100,
            packageId: $tenantBPackageId,
        );
    }

    public function test_save_rejects_a_soft_deleted_package(): void
    {
        $soft = Packages::factory()->create([
            'patient_id' => $this->patient->id,
            'location_id' => $this->defaultLocation->id,
            'total_price' => 100,
        ]);
        $softId = $soft->id;
        $soft->delete(); // soft-delete

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->callSave(cashAmount: 100, packageId: $softId);
    }

    /* -------- C7 — refund cap -------- */

    public function test_refund_rejects_an_amount_exceeding_total_paid_in(): void
    {
        // Patient paid in Rs. 5000 against this plan.
        PackageAdvances::factory()->create([
            'cash_flow' => 'in',
            'cash_amount' => 5000,
            'patient_id' => $this->patient->id,
            'package_id' => $this->package->id,
            'location_id' => $this->defaultLocation->id,
            'payment_mode_id' => $this->cash->id,
        ]);
        // Existing refund row to be edited.
        $refund = PackageAdvances::factory()->create([
            'cash_flow' => 'out',
            'cash_amount' => 1000,
            'is_refund' => 1,
            'patient_id' => $this->patient->id,
            'package_id' => $this->package->id,
            'location_id' => $this->defaultLocation->id,
            'payment_mode_id' => $this->cash->id,
        ]);

        $result = app(PlanRefundService::class)->processRefund([
            'package_id' => $this->package->id,
            'record_id' => $refund->id,
            'refund_amount' => 999_999_999, // way more than paid in
            'payment_mode_id' => $this->cash->id,
            'refund_note' => 'over-refund probe',
            'case_setteled' => '0',
            'created_at' => now()->format('Y-m-d'),
        ]);

        $this->assertFalse($result['success'],
            'PlanRefundService MUST reject a refund that exceeds the total ever paid in on the package.');
        $this->assertStringContainsString('exceeds', strtolower($result['message']));
    }

    public function test_refund_accepts_an_amount_within_paid_balance(): void
    {
        PackageAdvances::factory()->create([
            'cash_flow' => 'in',
            'cash_amount' => 5000,
            'patient_id' => $this->patient->id,
            'package_id' => $this->package->id,
            'location_id' => $this->defaultLocation->id,
            'payment_mode_id' => $this->cash->id,
        ]);
        $refund = PackageAdvances::factory()->create([
            'cash_flow' => 'out',
            'cash_amount' => 100,
            'is_refund' => 1,
            'patient_id' => $this->patient->id,
            'package_id' => $this->package->id,
            'location_id' => $this->defaultLocation->id,
            'payment_mode_id' => $this->cash->id,
        ]);

        $result = app(PlanRefundService::class)->processRefund([
            'package_id' => $this->package->id,
            'record_id' => $refund->id,
            'refund_amount' => 2000, // well within the 5000 cap
            'payment_mode_id' => $this->cash->id,
            'refund_note' => 'in-bounds probe',
            'case_setteled' => '0',
            'created_at' => now()->format('Y-m-d'),
        ]);

        $this->assertTrue($result['success']);
    }

    /* -------- H2 — cancel SoD -------- */

    public function test_creator_cannot_cancel_their_own_advance(): void
    {
        $creator = $this->actingAsAdmin();

        $advance = PackageAdvances::factory()->create([
            'cash_flow' => 'in',
            'cash_amount' => 500,
            'patient_id' => $this->patient->id,
            'package_id' => $this->package->id,
            'location_id' => $this->defaultLocation->id,
            'payment_mode_id' => $this->cash->id,
            'created_by' => $creator->id,
        ]);

        // Cancel returns a redirect; we want the abort response.
        $response = $this->post("/api/packagesadvances/cancel/{$advance->id}");
        $response->assertStatus(403);
    }

    /* -------- H5 — bulk-delete cross-tenant feedback -------- */

    public function test_bulk_delete_refuses_when_an_id_belongs_to_another_tenant(): void
    {
        $tenantBPackageId = $this->seedTenantBPackage();

        $result = app(PlanService::class)
            ->handleBulkDelete([$this->package->id, $tenantBPackageId]);

        $this->assertSame(0, $result['deleted']);
        $this->assertArrayHasKey('cross_tenant_ids', $result);
        $this->assertContains($tenantBPackageId, $result['cross_tenant_ids']);

        // And the in-tenant package must NOT have been deleted — the
        // mixed list is rejected wholesale, not partially applied.
        $this->assertNotNull(Packages::find($this->package->id));
    }

    /* --------------------------------------------------------------- */

    /**
     * Returns the tenant-B package id. Inserts via DB::table to bypass
     * the `GuardsTenantBoundary` trait that would otherwise rewrite the
     * `account_id` to the test's authenticated user (tenant A). The
     * trait is the right defence in production — the test just needs
     * to set up a cross-tenant row deliberately.
     */
    private function seedTenantBPackage(): int
    {
        \Illuminate\Support\Facades\DB::table('accounts')->insertOrIgnore([
            'id' => 2,
            'name' => 'Tenant B',
            'email' => 'b@x.test',
            'contact' => '0',
            'suspended' => '0',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $locId = \Illuminate\Support\Facades\DB::table('locations')->insertGetId([
            'account_id' => 2,
            'name' => 'Tenant B Loc',
            'active' => 1,
            'city_id' => 1,
            'region_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $patientId = \Illuminate\Support\Facades\DB::table('users')->insertGetId([
            'account_id' => 2,
            'name' => 'Tenant B Patient',
            'email' => 'tbp+'.uniqid().'@x.test',
            'password' => bcrypt('x'),
            'user_type_id' => 3,
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) \Illuminate\Support\Facades\DB::table('packages')->insertGetId([
            'random_id' => (string) \Illuminate\Support\Str::uuid(),
            'account_id' => 2,
            'name' => 'Tenant B Plan',
            'plan_name' => 'tenantB',
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
    }

    private function callSave(float $cashAmount, ?int $packageId = null): array
    {
        $request = Request::create('/finances/savepackagesadvances', 'POST', [
            'package_id' => $packageId ?? $this->package->id,
            'patient_id' => $this->patient->id,
            'payment_mode_id' => $this->cash->id,
            'cash_amount' => $cashAmount,
        ]);
        $request->setUserResolver(fn () => Auth::user());

        $response = app(PackageAdvancesController::class)->savepackagesadvances($request);

        return json_decode($response->getContent(), true) ?? [];
    }
}
