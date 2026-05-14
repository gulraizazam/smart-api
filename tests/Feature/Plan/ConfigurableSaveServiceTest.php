<?php

declare(strict_types=1);

namespace Tests\Feature\Plan;

use App\Services\Plan\PlanDiscountService;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * `PlanDiscountService::saveServiceForPlan` — configurable Buy/Get expansion.
 *
 * Pinned after the user observed that picking a categorical BUY discount
 * (e.g. "buy any session of body contouring, get 30% off CoolGlide Face")
 * on the plan-create dialog showed the correct preview but, on Add row,
 * silently materialised only the GET rows — the BUY row was missing from
 * the response.
 *
 * Root cause: `$baseServices->merge($getServices)` used Eloquent's
 * `Collection::merge`, which keys by primary key. `base_discount_services`
 * and `get_discount_services` are independent tables with independent
 * auto-increment IDs, so a collision is the common case (both tables
 * start AI=1; the first row in each gets id=1, and merge drops one). The
 * fix is `concat`, which preserves order without de-duping by PK.
 *
 * This test fixes the BUY+GETs in IDs that *will* collide under
 * RefreshDatabase (both tables freshly auto-incrementing from 1), then
 * asserts the response carries every row the operator was shown in the
 * preview. Without the fix, the BUY (id=1 in base_discount_services) is
 * clobbered by the GET (id=1 in get_discount_services) and the response
 * contains GETs only.
 */
class ConfigurableSaveServiceTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private PlanDiscountService $discountService;

    private int $patientId;

    private int $locationId;

    private int $buyServiceId;

    private int $getServiceId;

    private int $categoryId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();

        $this->discountService = app(PlanDiscountService::class);

        $this->locationId = (int) DB::table('locations')->insertGetId([
            'name' => 'Test Centre',
            'city_id' => 1,
            'region_id' => 1,
            'address' => '1 Test St',
            'tax_percentage' => 0,
            'account_id' => 1,
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->patientId = (int) DB::table('users')->insertGetId([
            'name' => 'Configurable Patient',
            'email' => 'configsave-'.uniqid().'@test.local',
            'password' => bcrypt('test'),
            'phone' => '+923000000060',
            'user_type_id' => 3,
            'account_id' => 1,
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->categoryId = (int) DB::table('services')->insertGetId([
            'name' => 'Body Contouring',
            'price' => 0,
            'end_node' => 0,
            'tax_treatment_type_id' => 1,
            'account_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->buyServiceId = (int) DB::table('services')->insertGetId([
            'name' => 'CoolSculpt Large',
            'price' => 24995,
            'end_node' => 1,
            'tax_treatment_type_id' => 1,
            'account_id' => 1,
            'parent_id' => $this->categoryId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getServiceId = (int) DB::table('services')->insertGetId([
            'name' => 'CoolGlide Face',
            'price' => 5995,
            'end_node' => 1,
            'tax_treatment_type_id' => 1,
            'account_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_save_returns_buy_and_get_rows_even_when_their_ids_collide(): void
    {
        // The exact scenario the user reported: categorical BUY ("any
        // session of body contouring") + a single priced GET. Under
        // RefreshDatabase, both child tables start AI=1 and the first
        // insert into each lands at id=1 — the PK collision that
        // Eloquent's merge() collapses. The fix uses concat() so both
        // rows survive into $mergedServices.
        $discountId = (int) DB::table('discounts')->insertGetId([
            'name' => 'Buy 1 BC, Get 30% off Face',
            'slug' => 'config-'.uniqid(),
            'type' => 'Configurable',
            'amount' => 0,
            'discount_type' => 'Treatment',
            'pre_days' => 0,
            'post_days' => 0,
            'start' => now()->subDay(),
            'end' => now()->addMonth(),
            'active' => 1,
            'account_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // BUY: categorical, body contouring, 1 session.
        DB::table('base_discount_services')->insert([
            'discount_id' => $discountId,
            'service_id' => $this->categoryId,
            'service_price' => 0,
            'sessions' => 1,
            'is_category' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // GET: 30% off CoolGlide Face, 1 session. With same_service=0
        // the save path resolves the service via the row's service_id;
        // we use a distinct GET service so the response rows are
        // unambiguously identifiable.
        DB::table('get_discount_services')->insert([
            'discount_id' => $discountId,
            'service_id' => $this->getServiceId,
            'base_service_id' => $this->categoryId,
            'service_price' => 0,
            'sessions' => 1,
            'discount_type' => 'percentage',
            'discount_amount' => 30,
            'same_service' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = $this->discountService->saveServiceForPlan([
            'service_id' => $this->buyServiceId,
            'discount_id' => $discountId,
            'patient_id' => $this->patientId,
            'location_id' => $this->locationId,
            'user_id' => $this->patientId,
            'random_id' => 'TEST-CFG-'.uniqid(),
            'package_total' => '0',
            'is_exclusive' => '0',
            'discount_price' => 0,
            'net_amount' => 24995,
        ]);

        $this->assertTrue($result['success'], 'saveServiceForPlan must succeed for a configurable discount');
        $this->assertTrue($result['data']['is_configurable'] ?? false);

        $rows = $result['data']['rows'] ?? [];
        $serviceNames = array_column($rows, 'service_name');

        $this->assertContains(
            'CoolSculpt Large',
            $serviceNames,
            'BUY row (the operator-picked service) must appear in the response. '
            .'Regression: Eloquent Collection::merge keyed by PK and silently dropped the BUY '
            .'when its id collided with a GET id.',
        );
        $this->assertContains(
            'CoolGlide Face',
            $serviceNames,
            'GET row must also appear; the fix must not drop the GET either.',
        );
        $this->assertCount(2, $rows, 'Exactly one BUY + one GET row expected.');
    }
}
