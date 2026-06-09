<?php

declare(strict_types=1);

namespace Tests\Feature\Plan;

use App\Services\Plan\PlanService;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * The service dropdown behind the plan-create dialog
 * (GET /api/packages/getservice -> PlanService::getServicesByLocation).
 *
 * The dialog's inline "Price / After discount" read-out shows the service
 * price the instant a service is picked, so the price has to ride along on
 * the dropdown rows. The query used to select only id/name/parent_id/active,
 * so the SPA read `undefined`, coerced it to NaN and rendered an em-dash
 * ("Price —") instead of a figure. Pinned here so a future column trim can't
 * silently drop `price` and re-break the preview.
 */
class ServiceCatalogPriceTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private PlanService $service;

    private int $locationId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
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
    }

    public function test_dropdown_rows_carry_price_so_the_inline_preview_has_a_figure(): void
    {
        // A bookable service is a leaf (parent_id > 0). getServicesByLocation
        // filters to leaves at the end, so the fixture has to be one.
        $categoryId = (int) DB::table('services')->insertGetId([
            'name' => 'Body Contouring',
            'price' => 0,
            // Root category — parent_id must be NULL (or a real service id)
            // to satisfy the services.parent_id self-FK; 0 has no matching row.
            'parent_id' => null,
            'end_node' => 0,
            'active' => 1,
            'tax_treatment_type_id' => 1,
            'account_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $serviceId = (int) DB::table('services')->insertGetId([
            'name' => 'CoolSculpt - large',
            'price' => 1500.00,
            'parent_id' => $categoryId,
            'end_node' => 1,
            'active' => 1,
            'tax_treatment_type_id' => 1,
            'account_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('service_has_locations')->insert([
            'service_id' => $serviceId,
            'location_id' => $this->locationId,
            'account_id' => 1,
        ]);

        $services = $this->service->getServicesByLocation($this->locationId, 1);

        $row = collect($services)->firstWhere('id', $serviceId);
        $this->assertNotNull($row, 'The assigned leaf service must appear in the dropdown.');
        $this->assertTrue(
            property_exists($row, 'price'),
            'Dropdown rows must carry price for the dialog inline preview.',
        );
        $this->assertEquals(1500.00, (float) $row->price);
    }
}
