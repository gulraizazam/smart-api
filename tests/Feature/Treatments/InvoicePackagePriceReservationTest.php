<?php

declare(strict_types=1);

namespace Tests\Feature\Treatments;

use App\Enums\AppointmentType;
use App\Helpers\ACL;
use App\Models\Appointments;
use App\Models\Packages;
use App\Models\PaymentModes;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * invoicePackagePrice (GET /api/treatment/invoice/{id}/package-price) feeds the
 * consume dialog's "Outstanding". It must hold back RESERVED money — a paid BUY
 * whose discounted/free GET was consumed ahead of it — so a newly-added service
 * shows its TRUE outstanding instead of "0". Before the fix the dialog showed
 * Outstanding 0 + "Settle from balance", and the saveinvoice reservation gate
 * only blocked the consume at the final Print click ("wrong approach", Shahid
 * 2026-06-15). Mirrors ConsumptionReservation::reservedRefundAmount.
 */
class InvoicePackagePriceReservationTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private int $patientId;

    private int $doctorId;

    private int $serviceId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $admin = $this->actingAsAdmin();
        // ACL::getUserCentres() memoises per user in a STATIC cache that isn't
        // rolled back between tests — flush it so each test recomputes fresh.
        ACL::flushMemo();
        // The appointment lookup (getAppointmentById) is scoped to the user's
        // assigned centres. A fresh admin is only id 1 (= "all centres") when
        // this test runs first; in the full suite AUTO_INCREMENT gives it a
        // higher id, so it must be explicitly assigned to this test's centre or
        // the lookup 404s.
        DB::table('user_has_locations')->insert([
            'user_id' => $admin->id,
            'location_id' => $this->defaultLocation->id,
            'region_id' => (int) $this->defaultLocation->region_id,
        ]);

        $this->patientId = (int) DB::table('users')->insertGetId([
            'account_id' => 1, 'name' => 'PkgPrice Patient',
            'email' => 'pkgprice-'.uniqid().'@test.local', 'password' => bcrypt('x'),
            'user_type_id' => 3, 'active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->doctorId = (int) DB::table('users')->insertGetId([
            'account_id' => 1, 'name' => 'PkgPrice Doctor',
            'email' => 'pkgdoc-'.uniqid().'@test.local', 'password' => bcrypt('x'),
            'user_type_id' => 5, 'active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->serviceId = (int) DB::table('services')->insertGetId([
            'name' => 'PkgPrice Svc '.uniqid(), 'price' => 5000,
            'tax_treatment_type_id' => 1, 'account_id' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_outstanding_holds_back_reserved_money_for_an_added_service(): void
    {
        // Out-of-order configurable: BUY (10000, order 1, unconsumed) + free GET
        // (0, order 3, consumed) → the 10000 BUY money is reserved. Operator
        // then adds a standalone 5000 service and pays only the original 10000.
        $plan = $this->makePlan();
        $this->configSession($plan, 7701, order: 1, sold: 10000, consumed: 0);
        $this->configSession($plan, 7701, order: 3, sold: 0, consumed: 1);
        $newService = $this->plainSession($plan, 5000);
        $this->pay($plan, 10000);

        $apptId = $this->makeTreatmentAppointment();

        $resp = $this->getJson("/api/treatment/invoice/{$apptId}/package-price?package_id={$plan->id}&package_service_id={$newService}");

        $resp->assertOk();
        $this->assertGreaterThan(
            0,
            (int) $resp->json('data.outstanding'),
            'The added service must show a real outstanding — reserved BUY money is not available to settle it (was 0 before the fix).',
        );
    }

    public function test_outstanding_is_zero_when_nothing_is_reserved(): void
    {
        // In-order (BUY consumed first) + genuine surplus balance → the added
        // service settles from balance, 0 outstanding (unchanged behaviour).
        $plan = $this->makePlan();
        $this->configSession($plan, 7702, order: 1, sold: 10000, consumed: 1);
        $newService = $this->plainSession($plan, 5000);
        $this->pay($plan, 20000);

        $apptId = $this->makeTreatmentAppointment();

        $resp = $this->getJson("/api/treatment/invoice/{$apptId}/package-price?package_id={$plan->id}&package_service_id={$newService}");

        $resp->assertOk();
        $this->assertSame(
            0,
            (int) $resp->json('data.outstanding'),
            'With nothing reserved and surplus balance, the added service has no outstanding.',
        );
    }

    public function test_service_line_dialog_ignores_an_id_colliding_multiple_bundle(): void
    {
        // Two identical SERVICE lines in one plan — same sold price, same catalog
        // service. The ONLY difference: row A's bundle_id collides with a
        // type='multiple' Package's id; row B's does not. Both are
        // source_type='service', so the consume-dialog math MUST be identical — a
        // service line is never gated by a Package it merely id-collides with.
        // Before the fix, :981 resolved the colliding Package and applied
        // bundle-math (wrong settle_amount + remaining) to the service line.
        $plan = $this->makePlan();

        $collidingBundleId = 991500;
        DB::table('bundles')->insert([
            'id' => $collidingBundleId, 'name' => 'Unrelated Package', 'price' => 50000,
            'services_price' => 50000, 'total_services' => 3, 'type' => 'multiple', 'active' => 1,
            'tax_treatment_type_id' => 1, 'account_id' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $colliding = $this->serviceSession($plan, sold: 5000, bundleId: $collidingBundleId);
        $clean = $this->serviceSession($plan, sold: 5000, bundleId: 999990);
        // Partial payment (below the 5000 service price) so the gate math engages.
        $this->pay($plan, 3000);

        $apptId = $this->makeTreatmentAppointment();
        $url = fn (int $psid): string => "/api/treatment/invoice/{$apptId}/package-price?package_id={$plan->id}&package_service_id={$psid}";

        $a = $this->getJson($url($colliding));
        $a->assertOk();
        $b = $this->getJson($url($clean));
        $b->assertOk();

        foreach (['service_price', 'outstanding', 'settle_amount', 'remaining', 'balance'] as $field) {
            $this->assertSame(
                $b->json("data.{$field}"),
                $a->json("data.{$field}"),
                "Service-line `{$field}` must not change because its bundle_id collides with a type=multiple Package.",
            );
        }
    }

    public function test_real_package_line_still_gets_bundle_math_in_the_dialog(): void
    {
        // Money-safety companion to the collision test: the source_type gate must
        // NOT disable legitimate Packages. A source_type='bundle' line and a
        // source_type='service' line sharing the SAME real type='multiple'
        // bundle_id must produce DIFFERENT dialog math — the Package gets
        // bundle-math (path B, remaining = full service value), the service gets
        // per-service (path A, remaining = 0). The OLD code wrongly applied
        // bundle-math to BOTH, so this is red against it and green after the fix.
        $plan = $this->makePlan();

        $packageId = 991600;
        DB::table('bundles')->insert([
            'id' => $packageId, 'name' => 'Real Package', 'price' => 50000,
            'services_price' => 50000, 'total_services' => 3, 'type' => 'multiple', 'active' => 1,
            'tax_treatment_type_id' => 1, 'account_id' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $bundleRow = $this->bundleSession($plan, sold: 5000, bundleId: $packageId);
        $serviceRow = $this->serviceSession($plan, sold: 5000, bundleId: $packageId);
        $this->pay($plan, 3000);

        $apptId = $this->makeTreatmentAppointment();
        $url = fn (int $psid): string => "/api/treatment/invoice/{$apptId}/package-price?package_id={$plan->id}&package_service_id={$psid}";

        $bundle = $this->getJson($url($bundleRow));
        $bundle->assertOk();
        $service = $this->getJson($url($serviceRow));
        $service->assertOk();

        $this->assertNotSame(
            (float) $service->json('data.remaining'),
            (float) $bundle->json('data.remaining'),
            'A source_type=bundle line must still receive bundle-math, distinct from a service line at the same id.',
        );
    }

    /* ===================== helpers ===================== */

    private function makePlan(): Packages
    {
        return Packages::factory()->create([
            'account_id' => 1, 'location_id' => $this->defaultLocation->id,
            'patient_id' => $this->patientId, 'plan_type' => 'plan',
            'total_price' => 0, 'random_id' => 'PKGP-'.uniqid(),
        ]);
    }

    private function makeTreatmentAppointment(): int
    {
        return (int) Appointments::factory()->create([
            'account_id' => 1,
            'appointment_type_id' => AppointmentType::Treatment->value,
            'patient_id' => $this->patientId,
            'location_id' => $this->defaultLocation->id,
            'doctor_id' => $this->doctorId,
            'service_id' => $this->serviceId,
        ])->id;
    }

    private function configSession(Packages $plan, int $configGroupId, int $order, float $sold, int $consumed): int
    {
        $bundleRowId = (int) DB::table('package_bundles')->insertGetId([
            'account_id' => 1, 'package_id' => $plan->id, 'bundle_id' => 999990, 'qty' => 1,
            'source_type' => 'service', 'config_group_id' => $configGroupId,
            'service_price' => $sold, 'net_amount' => $sold, 'tax_including_price' => $sold,
            'random_id' => $plan->random_id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $svcId = (int) DB::table('services')->insertGetId([
            'name' => 'Cfg Svc '.uniqid(), 'price' => $sold, 'tax_treatment_type_id' => 1,
            'account_id' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return (int) DB::table('package_services')->insertGetId([
            'package_id' => $plan->id, 'package_bundle_id' => $bundleRowId, 'service_id' => $svcId,
            'price' => $sold, 'orignal_price' => $sold, 'tax_including_price' => $sold,
            'tax_exclusive_price' => $sold, 'is_consumed' => $consumed, 'consumption_order' => $order,
            'random_id' => $plan->random_id, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function plainSession(Packages $plan, float $sold): int
    {
        $bundleRowId = (int) DB::table('package_bundles')->insertGetId([
            'account_id' => 1, 'package_id' => $plan->id, 'bundle_id' => 999990, 'qty' => 1,
            'source_type' => 'service', 'config_group_id' => null,
            'service_price' => $sold, 'net_amount' => $sold, 'tax_including_price' => $sold,
            'random_id' => $plan->random_id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return (int) DB::table('package_services')->insertGetId([
            'package_id' => $plan->id, 'package_bundle_id' => $bundleRowId, 'service_id' => $this->serviceId,
            'price' => $sold, 'orignal_price' => $sold, 'tax_including_price' => $sold,
            'tax_exclusive_price' => $sold, 'is_consumed' => 0, 'consumption_order' => 0,
            'random_id' => $plan->random_id, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function bundleSession(Packages $plan, float $sold, int $bundleId): int
    {
        // A genuine multi-service Package line (source_type='bundle') — bundle_id
        // is a real bundles.id. This SHOULD get the package bundle-math gate.
        $bundleRowId = (int) DB::table('package_bundles')->insertGetId([
            'account_id' => 1, 'package_id' => $plan->id, 'bundle_id' => $bundleId, 'qty' => 1,
            'source_type' => 'bundle', 'config_group_id' => null,
            'service_price' => $sold, 'net_amount' => $sold, 'tax_including_price' => $sold,
            'random_id' => $plan->random_id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return (int) DB::table('package_services')->insertGetId([
            'package_id' => $plan->id, 'package_bundle_id' => $bundleRowId, 'service_id' => $this->serviceId,
            'price' => $sold, 'orignal_price' => $sold, 'tax_including_price' => $sold,
            'tax_exclusive_price' => $sold, 'is_consumed' => 0, 'consumption_order' => 0,
            'random_id' => $plan->random_id, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function serviceSession(Packages $plan, float $sold, int $bundleId): int
    {
        $bundleRowId = (int) DB::table('package_bundles')->insertGetId([
            'account_id' => 1, 'package_id' => $plan->id, 'bundle_id' => $bundleId, 'qty' => 1,
            'source_type' => 'service', 'config_group_id' => null,
            'service_price' => $sold, 'net_amount' => $sold, 'tax_including_price' => $sold,
            'random_id' => $plan->random_id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return (int) DB::table('package_services')->insertGetId([
            'package_id' => $plan->id, 'package_bundle_id' => $bundleRowId, 'service_id' => $this->serviceId,
            'price' => $sold, 'orignal_price' => $sold, 'tax_including_price' => $sold,
            'tax_exclusive_price' => $sold, 'is_consumed' => 0, 'consumption_order' => 0,
            'random_id' => $plan->random_id, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function pay(Packages $plan, float $amount): void
    {
        $cash = PaymentModes::query()->where('name', 'Cash')->firstOrFail();
        DB::table('package_advances')->insert([
            'account_id' => 1, 'package_id' => $plan->id, 'patient_id' => $this->patientId,
            'payment_mode_id' => $cash->id, 'location_id' => $this->defaultLocation->id,
            'cash_flow' => 'in', 'cash_amount' => $amount, 'is_cancel' => 0, 'is_setteled' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
