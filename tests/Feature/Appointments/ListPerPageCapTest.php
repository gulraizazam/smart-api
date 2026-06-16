<?php

declare(strict_types=1);

namespace Tests\Feature\Appointments;

use App\Models\UserHasLocations;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * The SPA's consultation/treatment datatables offer a 200 rows-per-page
 * option. The controllers clamp `per_page` server-side, so the cap must be
 * at least 200 or picking "200" silently returns only the old cap (100) —
 * a confusing half-page. Pins the clamp ceiling for both list endpoints.
 */
class ListPerPageCapTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $admin = $this->actingAsAdmin();

        UserHasLocations::create([
            'user_id' => $admin->id,
            'region_id' => 1,
            'location_id' => $this->defaultLocation->id,
        ]);
    }

    public function test_consultancy_list_honours_per_page_up_to_200(): void
    {
        $response = $this->getJson('/api/consultancy?per_page=200');

        $response->assertOk();
        $this->assertSame(200, $response->json('meta.per_page'));
    }

    public function test_treatment_list_honours_per_page_up_to_200(): void
    {
        $response = $this->getJson('/api/treatment?per_page=200');

        $response->assertOk();
        $this->assertSame(200, $response->json('meta.per_page'));
    }
}
