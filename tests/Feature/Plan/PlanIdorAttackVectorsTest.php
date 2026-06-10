<?php

declare(strict_types=1);

namespace Tests\Feature\Plan;

use App\Models\Packages;
use App\Models\PackageService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * IDOR / cross-tenant attack vectors for the plan MUTATION + READ surface
 * that the 2026-06-10 audit found unscoped:
 *
 *   - POST packages/updatebundle              (updateBundlePayment)
 *   - POST packages/update_membership_plan    (updateMembershipPlan)
 *   - POST plans/deletepackagesservice        (deletePackageService)
 *   - POST plans/cascade-delete               (cascadeDeleteGroup)
 *   - POST plans/voucher/reserve              (reserveVoucherForPlan)
 *   - POST packages/updatesoldby              (updateSoldBy)
 *   - DELETE plans/destroy/{id}               (deletePlan)
 *   - GET  packages/display/{id}              (getDisplayData)
 *   - GET  packages/pdf/{id}                  (package_pdf)
 *
 * Every one of these resolved a client-supplied id with a bare
 * find()/findOrFail()/exists: — so a holder of ordinary plans.* perms
 * could record payments, delete rows, tamper voucher balances, reassign
 * commission, soft-delete plans, and read another tenant's patient PII +
 * payment ledger by guessing an id. Each test acts as an account-1
 * Super-Admin (so the permission gate is satisfied — this isolates the
 * ACCOUNT-SCOPING defence) and targets an account-2 row. If any test goes
 * red, a cross-tenant primitive on the plan surface has been reopened.
 *
 * Sibling suites: EditPaymentAttackVectorsTest, FakePaymentAttackVectorsTest,
 * PlansSecurityHardeningTest (the already-hardened cash surface).
 */
class PlanIdorAttackVectorsTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin(); // account_id = 1
    }

    /* ---------------- updatebundle (record payment) ---------------- */

    public function test_updatebundle_rejects_cross_tenant_package(): void
    {
        $b = $this->seedTenantB();

        $before = DB::table('package_advances')->where('package_id', $b['package_id'])->count();

        $response = $this->postJson('/api/packages/updatebundle', [
            'package_id' => $b['package_id'],
            'appointment_id' => $b['appointment_id'],
            'payment_mode_id' => $b['payment_mode_id'],
            'cash_amount' => 5000,
            'grand_total' => 5000,
        ]);

        $response->assertStatus(422);
        $this->assertSame(
            $before,
            DB::table('package_advances')->where('package_id', $b['package_id'])->count(),
            'A cross-tenant updatebundle must NOT record a payment on the victim plan.',
        );
    }

    /* ------------- update_membership_plan (record payment) ------------- */

    public function test_update_membership_plan_rejects_cross_tenant_package(): void
    {
        $b = $this->seedTenantB();

        $before = DB::table('package_advances')->where('package_id', $b['package_id'])->count();

        $response = $this->postJson('/api/packages/update_membership_plan', [
            'package_id' => $b['package_id'],
            'patient_id' => $b['patient_id'],
            'payment_mode_id' => $b['payment_mode_id'],
            'cash_amount' => 5000,
            'grand_total' => 5000,
        ]);

        $response->assertStatus(422);
        $this->assertSame(
            $before,
            DB::table('package_advances')->where('package_id', $b['package_id'])->count(),
            'A cross-tenant membership-plan update must NOT record a payment.',
        );
    }

    public function test_update_membership_plan_rejects_negative_cash_amount(): void
    {
        // Even on an OWNED plan, a crafted negative cash_amount must be
        // rejected at the boundary (it would shrink the branch cash pool
        // via the PackageAdvanceObserver).
        $ownPackage = Packages::factory()->create([
            'account_id' => 1,
            'location_id' => $this->defaultLocation->id,
            'plan_type' => 'membership',
        ]);

        $response = $this->postJson('/api/packages/update_membership_plan', [
            'package_id' => $ownPackage->id,
            'cash_amount' => -5000,
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('cash_amount', $response->json('errors', []));
    }

    /* ---------------- deletepackagesservice (delete row) ---------------- */

    public function test_deletepackagesservice_rejects_cross_tenant_row(): void
    {
        $b = $this->seedTenantB();
        $bundleId = $this->seedTenantBBundleRow($b);

        $response = $this->postJson('/api/plans/deletepackagesservice', [
            'id' => $bundleId,
            'update_status' => 1,
        ]);

        // Endpoint returns 200 with status:false for business-rule blocks;
        // the load-bearing assertion is that the row SURVIVES.
        $this->assertDatabaseHas('package_bundles', ['id' => $bundleId]);
        $this->assertFalse(
            (bool) $response->json('success'),
            'A cross-tenant row delete must not report success.',
        );
    }

    /* ------------------- cascade-delete (delete group) ------------------- */

    public function test_cascade_delete_rejects_cross_tenant_ids(): void
    {
        $b = $this->seedTenantB();
        $bundleId = $this->seedTenantBBundleRow($b);

        $response = $this->postJson('/api/plans/cascade-delete', [
            'ids' => [$bundleId],
            'patient_id' => $b['patient_id'],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseHas('package_bundles', ['id' => $bundleId]);
    }

    /* ------------------- voucher reserve (tamper balance) ------------------- */

    public function test_voucher_reserve_rejects_cross_tenant_patient(): void
    {
        $b = $this->seedTenantB();
        $voucher = $this->seedTenantBVoucher($b);

        $response = $this->postJson('/api/plans/voucher/reserve', [
            'voucher_id' => $voucher['discount_id'],
            'patient_id' => $b['patient_id'],
            'amount' => 1000,
        ]);

        $response->assertStatus(422);
        $this->assertSame(
            5000.0,
            (float) DB::table('user_vouchers')->where('id', $voucher['user_voucher_id'])->value('amount'),
            'A cross-tenant voucher reserve must NOT decrement the victim balance.',
        );
    }

    /* ------------------- updatesoldby (reassign commission) ------------------- */

    public function test_updatesoldby_rejects_cross_tenant_service(): void
    {
        $b = $this->seedTenantB();
        $serviceId = $this->seedTenantBServiceRow($b, soldBy: 999);

        $response = $this->postJson('/api/packages/updatesoldby', [
            'package_service_id' => $serviceId,
            'sold_by' => 1,
        ]);

        $this->assertSame(
            999,
            (int) DB::table('package_services')->where('id', $serviceId)->value('sold_by'),
            'A cross-tenant sold-by reassign must NOT change the attribution.',
        );
        $this->assertFalse((bool) $response->json('success'));
    }

    /* ------------------- destroy (soft-delete plan) ------------------- */

    public function test_destroy_rejects_cross_tenant_package(): void
    {
        $b = $this->seedTenantB();

        $response = $this->deleteJson("/api/plans/destroy/{$b['package_id']}");

        $this->assertNull(
            DB::table('packages')->where('id', $b['package_id'])->value('deleted_at'),
            'A cross-tenant destroy must NOT soft-delete the victim plan.',
        );
        $this->assertNotEquals(200, $response->status());
    }

    /* ------------------- display / pdf (read PII + ledger) ------------------- */

    public function test_display_rejects_cross_tenant_package(): void
    {
        $b = $this->seedTenantB();

        $response = $this->getJson("/api/packages/display/{$b['package_id']}");

        $this->assertNotEquals(
            true,
            $response->json('success'),
            'A cross-tenant display must NOT return another tenant\'s plan data.',
        );
        $this->assertNull($response->json('data.package.id'));
    }

    public function test_package_pdf_rejects_cross_tenant_package(): void
    {
        $b = $this->seedTenantB();

        $response = $this->get("/api/packages/pdf/{$b['package_id']}");

        $response->assertStatus(404);
    }

    /* ===================== fixtures ===================== */

    /**
     * @return array{patient_id:int, package_id:int, payment_mode_id:int, appointment_id:int, location_id:int}
     */
    private function seedTenantB(): array
    {
        DB::table('accounts')->insertOrIgnore([
            'id' => 2, 'name' => 'Tenant B', 'email' => 'b@x.test', 'contact' => '0',
            'suspended' => '0', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $locId = DB::table('locations')->insertGetId([
            'account_id' => 2, 'name' => 'B-loc', 'active' => 1,
            'city_id' => 1, 'region_id' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $patientId = DB::table('users')->insertGetId([
            'account_id' => 2, 'name' => 'B-patient',
            'email' => 'bp+'.uniqid().'@x.test', 'password' => bcrypt('x'),
            'user_type_id' => 3, 'active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $appointmentId = DB::table('appointments')->insertGetId([
            'account_id' => 2, 'patient_id' => $patientId, 'location_id' => $locId,
            'lead_id' => 0, 'doctor_id' => 0, 'region_id' => 1, 'city_id' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $packageId = DB::table('packages')->insertGetId([
            'random_id' => (string) Str::uuid(), 'account_id' => 2,
            'name' => 'B-plan', 'plan_name' => 'b', 'sessioncount' => 1,
            'total_price' => 1000, 'is_exclusive' => 0, 'plan_type' => 'membership',
            'patient_id' => $patientId, 'location_id' => $locId, 'active' => 1,
            'appointment_id' => $appointmentId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $paymentModeId = DB::table('payment_modes')->insertGetId([
            'account_id' => 2, 'name' => 'B-Cash', 'active' => 1, 'type' => 'application',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return [
            'patient_id' => (int) $patientId,
            'package_id' => (int) $packageId,
            'payment_mode_id' => (int) $paymentModeId,
            'appointment_id' => (int) $appointmentId,
            'location_id' => (int) $locId,
        ];
    }

    /** Seed a package_bundles row belonging to tenant B's plan. */
    private function seedTenantBBundleRow(array $b): int
    {
        return (int) DB::table('package_bundles')->insertGetId([
            'account_id' => 2,
            'package_id' => $b['package_id'],
            'bundle_id' => 1,
            'qty' => 1,
            'source_type' => 'service',
            'service_price' => 1000,
            'tax_including_price' => 1000,
            'net_amount' => 1000,
            'random_id' => (string) Str::uuid(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** Seed a package_services row belonging to tenant B's plan. */
    private function seedTenantBServiceRow(array $b, int $soldBy): int
    {
        return (int) DB::table('package_services')->insertGetId([
            'package_id' => $b['package_id'],
            'random_id' => (string) Str::uuid(),
            'price' => 1000,
            'sold_by' => $soldBy,
            'is_consumed' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * Seed a discount (voucher) + user_vouchers balance for tenant B's patient.
     *
     * @return array{discount_id:int, user_voucher_id:int}
     */
    private function seedTenantBVoucher(array $b): array
    {
        $discountId = (int) DB::table('discounts')->insertGetId([
            'account_id' => 2, 'name' => 'B-voucher', 'type' => 'Fixed', 'amount' => 5000,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $userVoucherId = (int) DB::table('user_vouchers')->insertGetId([
            'voucher_id' => $discountId, 'user_id' => $b['patient_id'],
            'amount' => 5000, 'total_amount' => 5000,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return ['discount_id' => $discountId, 'user_voucher_id' => $userVoucherId];
    }
}
