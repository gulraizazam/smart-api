<?php

declare(strict_types=1);

namespace Tests\Feature\Invoices;

use App\Models\Invoices;
use App\Models\Locations;
use App\Models\Patients;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * GET /api/invoices?invoice_status_slug=… — the stable, tenant-independent
 * way the SPA "Cancelled" quick filter targets a status. Status ids drift
 * per tenant, so the SPA must not be forced to discover the cancelled id
 * from harvested rows; the slug is the contract. These tests pin:
 *   - the slug narrows results to the matching status only
 *   - an unmatched slug genuinely filters (returns nothing) rather than
 *     being silently ignored — which would make the quick filter a no-op
 */
class InvoiceIndexStatusSlugFilterTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
    }

    public function test_invoice_status_slug_narrows_to_the_matching_status(): void
    {
        $admin = $this->actingAsAdmin();

        $location = Locations::factory()->create();
        // Non-superadmin test users are scoped via user_has_locations
        // (ACL::getUserCentres falls through to it); without this the
        // index would return nothing regardless of the slug filter.
        DB::table('user_has_locations')->updateOrInsert(
            ['user_id' => $admin->id, 'location_id' => $location->id],
            ['region_id' => 1],
        );

        $patient = Patients::factory()->create();

        $cancelled = Invoices::factory()->cancelled()->create([
            'patient_id' => $patient->id,
            'location_id' => $location->id,
        ]);
        // Pending (status 1) — must be excluded by the cancelled slug.
        Invoices::factory()->create([
            'patient_id' => $patient->id,
            'location_id' => $location->id,
        ]);

        $res = $this->getJson('/api/invoices?invoice_status_slug=cancelled');

        $res->assertOk();
        $res->assertJsonPath('data.meta.total', 1);
        $this->assertSame(
            [$cancelled->id],
            collect($res->json('data.items'))->pluck('id')->all(),
            'Only the invoice whose status slug is "cancelled" must be returned.',
        );
    }

    public function test_unmatched_slug_filters_rather_than_being_ignored(): void
    {
        $admin = $this->actingAsAdmin();

        $location = Locations::factory()->create();
        DB::table('user_has_locations')->updateOrInsert(
            ['user_id' => $admin->id, 'location_id' => $location->id],
            ['region_id' => 1],
        );

        Invoices::factory()->create(['location_id' => $location->id]);

        $res = $this->getJson('/api/invoices?invoice_status_slug=not-a-real-slug');

        $res->assertOk();
        $res->assertJsonPath(
            'data.meta.total',
            0,
            'An unknown slug must filter the list to nothing — proving the '
            .'param is applied, not silently dropped.',
        );
    }
}
