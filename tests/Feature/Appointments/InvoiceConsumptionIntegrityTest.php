<?php

declare(strict_types=1);

namespace Tests\Feature\Appointments;

use App\Models\Packages;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Consumption-integrity guards on AppointmentInvoiceController::saveinvoice
 * (POST /api/treatment/invoice) — the path that burns a plan session and
 * writes its invoice + cash-out settle pair.
 *
 * The 2026-06-10 audit found this path flipped `is_consumed` with a blind
 * `update()` by raw id — no already-consumed check, no plan/tenant scope,
 * no transaction/idempotency. Prod data showed 11 same-session/same-amount
 * duplicate invoice pairs within 5 minutes (double-submit / retry =
 * double-billing) and 222 sessions on >1 live invoice.
 *
 * These tests pin the contained guards that close the realistic cases:
 *   - an already-consumed session cannot be invoiced a second time, and
 *   - a session that belongs to another plan / tenant cannot be consumed
 *     while the invoice is written against the caller's package_id.
 *
 * Both guards return BEFORE any invoice/advance write, so a regression
 * (reverting the guard) lets the request fall through to the write
 * machinery and these assertions go red. A true microsecond race on the
 * same session is out of scope here — that needs a lockForUpdate claim
 * ahead of the invoice write (flagged follow-up), not a feature test.
 */
class InvoiceConsumptionIntegrityTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin(); // account_id = 1, Super-Admin → gate passes
    }

    public function test_already_consumed_session_cannot_be_invoiced_again(): void
    {
        $plan = Packages::factory()->create([
            'account_id' => 1,
            'location_id' => $this->defaultLocation->id,
        ]);
        $serviceId = $this->makeSession($plan->id, isConsumed: 1);

        $response = $this->postJson('/api/treatment/invoice', [
            'appointment_id' => 999999,
            'package_id' => $plan->id,
            'package_service_id' => $serviceId,
            'cash' => 0,
            'settle' => 0,
        ]);

        $this->assertFalse((bool) $response->json('success'));
        $this->assertSame(1, (int) $response->json('errors.already_consumed'),
            'A second invoice on an already-consumed session must be refused with the already_consumed flag.');
        $this->assertSame(
            0,
            DB::table('invoice_details')->where('package_id', $plan->id)->count(),
            'The refused re-consume must NOT write a second invoice against the plan.',
        );
    }

    public function test_session_from_another_plan_cannot_be_consumed(): void
    {
        // Session lives on plan A; request claims plan B (same account).
        $planA = Packages::factory()->create(['account_id' => 1, 'location_id' => $this->defaultLocation->id]);
        $planB = Packages::factory()->create(['account_id' => 1, 'location_id' => $this->defaultLocation->id]);
        $serviceOnA = $this->makeSession($planA->id, isConsumed: 0);

        $response = $this->postJson('/api/treatment/invoice', [
            'appointment_id' => 999999,
            'package_id' => $planB->id,
            'package_service_id' => $serviceOnA,
            'cash' => 0,
            'settle' => 0,
        ]);

        $response->assertStatus(404);
        $this->assertSame(
            0,
            (int) DB::table('package_services')->where('id', $serviceOnA)->value('is_consumed'),
            'A cross-plan consume must NOT burn the foreign session.',
        );
        $this->assertSame(0, DB::table('invoice_details')->where('package_id', $planB->id)->count());
    }

    public function test_session_from_another_tenant_cannot_be_consumed(): void
    {
        // Plan + session belong to account 2; caller is account 1.
        $tenantBPlanId = $this->seedTenantBPlan();
        $serviceOnB = $this->makeSession($tenantBPlanId, isConsumed: 0);

        $response = $this->postJson('/api/treatment/invoice', [
            'appointment_id' => 999999,
            'package_id' => $tenantBPlanId,
            'package_service_id' => $serviceOnB,
            'cash' => 0,
            'settle' => 0,
        ]);

        $response->assertStatus(404);
        $this->assertSame(
            0,
            (int) DB::table('package_services')->where('id', $serviceOnB)->value('is_consumed'),
            'A cross-tenant consume must NOT burn the foreign session.',
        );
    }

    /** Insert a package_services row on the given plan. */
    private function makeSession(int $packageId, int $isConsumed): int
    {
        return (int) DB::table('package_services')->insertGetId([
            'package_id' => $packageId,
            'random_id' => (string) Str::uuid(),
            'price' => 1000,
            'tax_including_price' => 1000,
            'is_consumed' => $isConsumed,
            'consumption_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Seed a minimal account-2 plan. */
    private function seedTenantBPlan(): int
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

        return (int) DB::table('packages')->insertGetId([
            'random_id' => (string) Str::uuid(), 'account_id' => 2,
            'name' => 'B-plan', 'plan_name' => 'b', 'sessioncount' => 1,
            'total_price' => 1000, 'is_exclusive' => 0, 'plan_type' => 'plan',
            'patient_id' => $patientId, 'location_id' => $locId, 'active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
