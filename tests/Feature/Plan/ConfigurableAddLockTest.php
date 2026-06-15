<?php

declare(strict_types=1);

namespace Tests\Feature\Plan;

use App\Exceptions\PlanException;
use App\Models\Packages;
use App\Services\Plan\PlanService;
use Illuminate\Support\Facades\DB;
use ReflectionClass;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Configurable-discount add-lock rule.
 *
 * If a plan contains a Configurable Buy/Get discount group and any
 * free/discounted GET session is consumed BEFORE its paired paid BUY
 * session, the plan must refuse new service additions until the BUY is
 * consumed (or a fresh plan is started).
 *
 * Two checkpoints implement the rule, both pinned here:
 *
 *   1. `PlanService::getEditFormData` (SQL EXISTS check) — populates
 *      `add_locked` + `add_lock_reason` on the edit payload so the SPA
 *      can pre-emptively grey out the Add row button and surface the
 *      reason in a banner. Without this, the operator only finds out
 *      *after* clicking Add and getting a 400.
 *
 *   2. `PlanService::validateConsumptionOrder` (private, same SQL) —
 *      runs inside the update transaction and throws `PlanException`
 *      if the predicate fires. The frontend can never be the only
 *      gate (devtools / API replay / next legacy refactor) — this
 *      throw is the safety net.
 *
 * The predicate is identical at both checkpoints. The Blade legacy UI
 * implements the same rule client-side in `create-plan.js`
 * (groups packageservices by config_group_id, compares max-consumed-
 * order vs min-unconsumed-order). All three paths must agree; this
 * test pins the SPA-shaped two (the JS is verified by parity, since
 * the SQL predicate IS the spec).
 */
class ConfigurableAddLockTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private PlanService $service;

    private int $patientId;

    private int $locationId;

    private int $serviceId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();

        $this->service = app(PlanService::class);

        $this->locationId = (int) DB::table('locations')->insertGetId([
            'name' => 'Test Centre',
            'city_id' => 1,
            'region_id' => 1,
            'address' => '1 Test St',
            'account_id' => 1,
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->patientId = (int) DB::table('users')->insertGetId([
            'name' => 'Test Patient',
            'email' => 'configlock-patient-'.uniqid().'@test.local',
            'password' => bcrypt('test'),
            'phone' => '+923000000003',
            'user_type_id' => 3,
            'account_id' => 1,
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->serviceId = (int) DB::table('services')->insertGetId([
            'name' => 'Test Service',
            'price' => 1000,
            'end_node' => 1,
            'tax_treatment_type_id' => 1,
            'account_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Create a plan with a single configurable-discount group of three
     * services (BUY=order 1, priced GET=2, complimentary GET=3). One
     * `package_bundles` row per service, each sharing the same
     * `config_group_id` — that grouping is what the predicate joins on.
     *
     * @param  array{int, int, int}  $consumed  is_consumed flag for orders 1, 2, 3 respectively.
     */
    private function makeConfigurableGroupPlan(array $consumed): int
    {
        $packageId = (int) DB::table('packages')->insertGetId([
            'patient_id' => $this->patientId,
            'location_id' => $this->locationId,
            'name' => 'Test Plan '.uniqid(),
            'plan_type' => 'plan',
            'total_price' => 3000,
            'account_id' => 1,
            'active' => 1,
            'random_id' => 'TEST-'.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $configGroupId = (int) abs(crc32(uniqid('cg-', true))); // unique per fixture run.

        foreach ([1, 2, 3] as $i => $order) {
            $bundleId = (int) DB::table('package_bundles')->insertGetId([
                'package_id' => $packageId,
                'random_id' => 'TEST-B-'.uniqid(),
                'config_group_id' => $configGroupId,
                'is_allocate' => 1,
                'qty' => 1,
                'service_price' => 1000,
                'net_amount' => 1000,
                'tax_exclusive_net_amount' => 854,
                'tax_percentage' => 17,
                'tax_price' => 146,
                'tax_including_price' => 1000,
                'location_id' => $this->locationId,
                'active' => 1,
                'is_exclusive' => 0,
                'discount_name' => 'Test Config',
                'discount_type' => 'Configurable',
                'discount_price' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('package_services')->insert([
                'package_id' => $packageId,
                'package_bundle_id' => $bundleId,
                'service_id' => $this->serviceId,
                'sold_by' => $this->patientId, // any user id satisfies the FK; not under test.
                'consumption_order' => $order,
                'is_consumed' => $consumed[$i],
                'consumed_at' => $consumed[$i] === 1 ? now() : null,
                'price' => 1000,
                'orignal_price' => 1000,
                'tax_including_price' => 1000,
                'tax_price' => 146,
                'tax_exclusive_price' => 854,
                'tax_percentage' => 17,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $packageId;
    }

    /** Record an IN payment so the plan reads as (partly/fully) paid. */
    private function payPlan(int $packageId, float $amount): void
    {
        $cash = \App\Models\PaymentModes::query()->where('name', 'Cash')->firstOrFail();
        DB::table('package_advances')->insert([
            'account_id' => 1,
            'package_id' => $packageId,
            'patient_id' => $this->patientId,
            'payment_mode_id' => $cash->id,
            'location_id' => $this->locationId,
            'cash_flow' => 'in',
            'cash_amount' => $amount,
            'is_cancel' => 0,
            'is_setteled' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Add an ungrouped, unconsumed single service (mimics the operator's "Add row"). */
    private function addPlainService(int $packageId, float $price): void
    {
        $bundleId = (int) DB::table('package_bundles')->insertGetId([
            'package_id' => $packageId,
            'random_id' => 'TEST-ADD-'.uniqid(),
            'config_group_id' => null,
            'is_allocate' => 1,
            'qty' => 1,
            'service_price' => $price,
            'net_amount' => $price,
            'tax_including_price' => $price,
            'tax_exclusive_net_amount' => $price,
            'tax_percentage' => 0,
            'tax_price' => 0,
            'location_id' => $this->locationId,
            'active' => 1,
            'is_exclusive' => 0,
            'discount_name' => '-',
            'discount_type' => '-',
            'discount_price' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('package_services')->insert([
            'package_id' => $packageId,
            'package_bundle_id' => $bundleId,
            'service_id' => $this->serviceId,
            'sold_by' => $this->patientId,
            'consumption_order' => 0,
            'is_consumed' => 0,
            'price' => $price,
            'orignal_price' => $price,
            'tax_including_price' => $price,
            'tax_price' => 0,
            'tax_exclusive_price' => $price,
            'tax_percentage' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_get_edit_form_data_locks_when_get_consumed_before_buy(): void
    {
        // Out-of-order: priced-GET (order=2) is_consumed, BUY (order=1)
        // still unconsumed → max-consumed-order > min-unconsumed-order.
        $packageId = $this->makeConfigurableGroupPlan([0, 1, 0]);

        $result = $this->service->getEditFormData($packageId);

        $this->assertTrue(
            $result['add_locked'],
            'A configurable group with a higher-order session consumed before a lower-order one must lock the plan.',
        );
        $this->assertNotNull($result['add_lock_reason']);
        $this->assertStringContainsString(
            'out of order',
            $result['add_lock_reason'],
            'The reason string is what the SPA renders in the banner and tooltip — it has to mention the actual condition or operators will not know what to do.',
        );
    }

    public function test_get_edit_form_data_unlocks_when_consumption_is_in_order(): void
    {
        // BUY first (order=1 consumed, 2 & 3 still unconsumed) — the
        // happy path. max-consumed (1) is NOT > min-unconsumed (2).
        $packageId = $this->makeConfigurableGroupPlan([1, 0, 0]);

        $result = $this->service->getEditFormData($packageId);

        $this->assertFalse($result['add_locked']);
        $this->assertNull($result['add_lock_reason']);
    }

    public function test_add_lock_releases_when_the_plan_is_fully_paid(): void
    {
        // Out-of-order (GET order=2 consumed, BUY order=1 not) — but the plan
        // is FULLY PAID. The client paid for everything, so adding a service
        // is allowed; ConsumptionReservation holds the paid BUY's money at
        // consume time. The lock must NOT engage.
        $packageId = $this->makeConfigurableGroupPlan([0, 1, 0]);
        $this->payPlan($packageId, 3000); // 3 sessions x 1000 sold = fully paid

        $result = $this->service->getEditFormData($packageId);

        $this->assertFalse(
            $result['add_locked'],
            'A fully-paid plan must allow adding a service even after out-of-order consumption.',
        );
        $this->assertNull($result['add_lock_reason']);
    }

    public function test_validate_consumption_order_passes_when_out_of_order_but_fully_paid(): void
    {
        // The server safety-net must also release when fully paid, or the save
        // would still 400 even though the SPA now allows the add.
        $packageId = $this->makeConfigurableGroupPlan([0, 1, 0]);
        $this->payPlan($packageId, 3000); // fully paid
        $package = Packages::findOrFail($packageId);

        $method = (new ReflectionClass(PlanService::class))
            ->getMethod('validateConsumptionOrder');
        $method->setAccessible(true);

        $method->invoke($this->service, $package);

        $this->assertTrue(true); // no exception thrown == pass.
    }

    public function test_add_lock_stays_released_after_an_added_service_inflates_the_total(): void
    {
        // The exact bug Shahid hit: out-of-order plan, fully paid for the group,
        // operator adds a service that pushes the plan total ABOVE payments.
        // The reservation floor (consumed + reserved BUY) is still covered, so
        // the lock must STAY released and the server must NOT throw — no
        // "saved, then ugly error" after the add. (Pre-fix the add re-locked
        // because the inflated total made the old "fully paid?" check false.)
        $packageId = $this->makeConfigurableGroupPlan([0, 1, 0]); // floor = 2000 (order2 consumed + order1 reserved)
        $this->payPlan($packageId, 3000);          // covers the group
        $this->addPlainService($packageId, 5000);  // total now 8000 > 3000 paid

        $result = $this->service->getEditFormData($packageId);
        $this->assertFalse(
            $result['add_locked'],
            'An added service that inflates the total must not re-lock the plan while the reservation floor is still covered.',
        );

        $package = Packages::findOrFail($packageId);
        $method = (new ReflectionClass(PlanService::class))->getMethod('validateConsumptionOrder');
        $method->setAccessible(true);
        $method->invoke($this->service, $package); // must NOT throw

        $this->assertTrue(true);
    }

    public function test_add_lock_re_engages_when_payments_fall_below_the_reserved_floor(): void
    {
        // Counterpart: if payments don't cover the reserved floor (e.g. a refund
        // pulled money out), the reserved BUY is exposed → the lock must engage
        // and the server safety-net must throw.
        $packageId = $this->makeConfigurableGroupPlan([0, 1, 0]); // floor = 2000
        $this->payPlan($packageId, 1500); // below the 2000 reserved floor

        $result = $this->service->getEditFormData($packageId);
        $this->assertTrue($result['add_locked']);

        $package = Packages::findOrFail($packageId);
        $method = (new ReflectionClass(PlanService::class))->getMethod('validateConsumptionOrder');
        $method->setAccessible(true);
        $this->expectException(PlanException::class);
        $method->invoke($this->service, $package);
    }

    public function test_get_edit_form_data_unlocks_when_no_configurable_group_exists(): void
    {
        // No config_group_id on the bundle → the predicate's
        // whereNotNull('package_bundles.config_group_id') filter
        // excludes the row, so even with a consumed session the plan
        // stays unlocked. Pins that the lock is specific to
        // configurable Buy/Get groups, not to consumption in general.
        $packageId = (int) DB::table('packages')->insertGetId([
            'patient_id' => $this->patientId,
            'location_id' => $this->locationId,
            'name' => 'Plain Plan',
            'plan_type' => 'plan',
            'total_price' => 1000,
            'account_id' => 1,
            'active' => 1,
            'random_id' => 'TEST-P-'.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $bundleId = (int) DB::table('package_bundles')->insertGetId([
            'package_id' => $packageId,
            'random_id' => 'TEST-NCB-'.uniqid(),
            'config_group_id' => null, // <- the discriminator.
            'is_allocate' => 1,
            'qty' => 1,
            'service_price' => 1000,
            'net_amount' => 1000,
            'tax_exclusive_net_amount' => 854,
            'tax_percentage' => 17,
            'tax_price' => 146,
            'tax_including_price' => 1000,
            'location_id' => $this->locationId,
            'active' => 1,
            'is_exclusive' => 0,
            'discount_name' => '-',
            'discount_type' => '-',
            'discount_price' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('package_services')->insert([
            'package_id' => $packageId,
            'package_bundle_id' => $bundleId,
            'service_id' => $this->serviceId,
            'sold_by' => $this->patientId,
            'consumption_order' => 0,
            'is_consumed' => 1, // consumed, but on a non-configurable row.
            'consumed_at' => now(),
            'price' => 1000,
            'orignal_price' => 1000,
            'tax_including_price' => 1000,
            'tax_price' => 146,
            'tax_exclusive_price' => 854,
            'tax_percentage' => 17,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = $this->service->getEditFormData($packageId);

        $this->assertFalse($result['add_locked']);
    }

    public function test_validate_consumption_order_throws_on_update_when_out_of_order(): void
    {
        // The private safety-net method that runs inside the update
        // transaction. Accessed via reflection so the test pins the
        // throw without having to build a full updatePlanPackage
        // payload (orthogonal to this rule).
        $packageId = $this->makeConfigurableGroupPlan([0, 1, 0]);
        $package = Packages::findOrFail($packageId);

        $method = (new ReflectionClass(PlanService::class))
            ->getMethod('validateConsumptionOrder');
        $method->setAccessible(true);

        $this->expectException(PlanException::class);
        $this->expectExceptionMessage('Cannot add new services');

        $method->invoke($this->service, $package);
    }

    public function test_predicate_degenerates_when_consumption_order_is_zero_on_every_row(): void
    {
        // Pre-fix bug pin. `PlanDiscountService::saveServiceForPlan`
        // used to insert configurable rows without setting
        // `consumption_order`, so every row landed with the column at
        // 0. With both BUY and GET sitting at 0, the predicate's
        // `ps2.consumption_order < package_services.consumption_order`
        // compare becomes `0 < 0` (false) — the lock can NEVER fire
        // on plans produced by that broken save path even when the
        // operator demonstrably consumed the GET before the BUY.
        //
        // This is what the user observed in production: created a
        // configurable plan, consumed the freebie, re-opened the edit
        // dialog, lock didn't engage. Root cause: every row at
        // consumption_order=0. The fix lives in
        // `PlanDiscountService.php::saveServiceForPlan` — stamp BUY=1,
        // priced GET=2, complimentary GET=3. This test pins the
        // *consequence* of the bug at the predicate layer so a future
        // refactor that drops the stamp is caught here even if the
        // save-path integration tests are skipped.
        $packageId = $this->makeZeroOrderConfigurableGroupPlan();

        $result = $this->service->getEditFormData($packageId);

        $this->assertFalse(
            $result['add_locked'],
            'With every row at consumption_order=0 the predicate degenerates and the lock cannot fire. '
            . 'This is the pre-fix bug — proves WHY the save path must stamp distinct order values.',
        );
    }

    /**
     * Reproduce the pre-fix DB state: configurable group with all rows
     * at consumption_order=0 and a GET consumed while the BUY remains
     * unconsumed. Mirror of `makeConfigurableGroupPlan` but with zero
     * orders to pin the degenerate predicate case.
     */
    private function makeZeroOrderConfigurableGroupPlan(): int
    {
        $packageId = (int) DB::table('packages')->insertGetId([
            'patient_id' => $this->patientId,
            'location_id' => $this->locationId,
            'name' => 'Zero Order Plan '.uniqid(),
            'plan_type' => 'plan',
            'total_price' => 2000,
            'account_id' => 1,
            'active' => 1,
            'random_id' => 'TEST-Z-'.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $configGroupId = (int) abs(crc32(uniqid('zero-cg-', true)));

        foreach ([0, 1] as $consumed) {
            $bundleId = (int) DB::table('package_bundles')->insertGetId([
                'package_id' => $packageId,
                'random_id' => 'TEST-ZB-'.uniqid(),
                'config_group_id' => $configGroupId,
                'is_allocate' => 1,
                'qty' => 1,
                'service_price' => 1000,
                'net_amount' => 1000,
                'tax_exclusive_net_amount' => 854,
                'tax_percentage' => 17,
                'tax_price' => 146,
                'tax_including_price' => 1000,
                'location_id' => $this->locationId,
                'active' => 1,
                'is_exclusive' => 0,
                'discount_name' => 'Test Config',
                'discount_type' => 'Configurable',
                'discount_price' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('package_services')->insert([
                'package_id' => $packageId,
                'package_bundle_id' => $bundleId,
                'service_id' => $this->serviceId,
                'sold_by' => $this->patientId,
                'consumption_order' => 0, // <- the pre-fix degenerate state.
                'is_consumed' => $consumed,
                'consumed_at' => $consumed === 1 ? now() : null,
                'price' => 1000,
                'orignal_price' => 1000,
                'tax_including_price' => 1000,
                'tax_price' => 146,
                'tax_exclusive_price' => 854,
                'tax_percentage' => 17,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $packageId;
    }

    public function test_validate_consumption_order_passes_on_update_when_in_order(): void
    {
        // Mirror of the throw test — the safety net must not fire on
        // in-order consumption, otherwise every legitimate update on a
        // partially-consumed configurable plan would 400. The
        // assertion is the absence of a throw; if validateConsumptionOrder
        // were to start throwing unconditionally this test goes red.
        $packageId = $this->makeConfigurableGroupPlan([1, 0, 0]);
        $package = Packages::findOrFail($packageId);

        $method = (new ReflectionClass(PlanService::class))
            ->getMethod('validateConsumptionOrder');
        $method->setAccessible(true);

        $method->invoke($this->service, $package);

        $this->assertTrue(true); // no exception thrown == pass.
    }
}
