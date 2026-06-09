<?php

declare(strict_types=1);

namespace Tests\Feature\Plan;

use App\Services\Plan\PlanDiscountService;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * crm3 must stamp `source_type = 'service'` on the package_bundles row it
 * persists for a single-service plan line — matching crm2, so that crm2
 * and every source_type-aware consumer (invoices, reports, name lookups)
 * resolve the right catalog for the overloaded `bundle_id`. Before the fix
 * `PlanDiscountService::saveServiceForPlan` left source_type NULL.
 */
class SaveServiceSourceTypeTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private PlanDiscountService $discountService;

    private int $patientId;

    private int $locationId;

    private int $serviceId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();

        $this->discountService = app(PlanDiscountService::class);

        $this->locationId = (int) DB::table('locations')->insertGetId([
            'name' => 'SourceType Centre',
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
            'name' => 'SourceType Patient',
            'email' => 'srctype-'.uniqid().'@test.local',
            'password' => bcrypt('test'),
            'phone' => '+923000000061',
            'user_type_id' => 3,
            'account_id' => 1,
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->serviceId = (int) DB::table('services')->insertGetId([
            'name' => 'Carbon Laser Facial',
            'price' => 9995,
            'end_node' => 1,
            'tax_treatment_type_id' => 1,
            'account_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_simple_single_service_save_stamps_source_type_service(): void
    {
        $randomId = 'SRCTYPE-SIMPLE-'.uniqid();

        $result = $this->discountService->saveServiceForPlan([
            'service_id' => $this->serviceId,
            'patient_id' => $this->patientId,
            'location_id' => $this->locationId,
            'user_id' => $this->patientId,
            'random_id' => $randomId,
            'package_total' => '0',
            'is_exclusive' => '0',
            'discount_price' => 0,
            'net_amount' => 9995,
        ]);

        $this->assertTrue($result['success'] ?? false, 'simple single-service save must succeed');

        $rows = DB::table('package_bundles')->where('random_id', $randomId)->get();
        $this->assertNotEmpty($rows, 'a package_bundles row must be persisted for the staged service');

        foreach ($rows as $row) {
            $this->assertSame(
                'service',
                $row->source_type,
                'crm3 must stamp source_type=service on a single-service plan line so crm2 + '
                .'source_type-aware consumers resolve the overloaded bundle_id against the services catalog.',
            );
        }
    }
}
