<?php

declare(strict_types=1);

namespace Tests\Feature\Bundle;

use App\Http\Requests\Bundle\StoreBundleRequest;
use App\Models\BundleHasServices;
use App\Models\Services;
use App\Services\Bundle\BundleService;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Pins the load-bearing contract that a **Package** stores per-service
 * QUANTITY as REPEATED `bundle_has_services` rows — those repeats are NOT
 * duplicates.
 *
 * Naming reality (easy to confuse — see memory project_packages_bundles_naming):
 *   - "Packages" (the /packages screen) → `bundles` + `bundle_has_services`
 *     via BundleService. A group of services; quantity = repeated rows
 *     (there is NO qty column, so the row count IS the session count).
 *   - "Bundles" (the /bundles screen) → a SEPARATE product in
 *     `service_bundles` (a clean `sessions` integer): "Nx of ONE service".
 *
 * A past `SELECT DISTINCT *` rebuild once destroyed multi-session packages
 * (e.g. "CoolGlide - 6 sessions" collapsed 6 rows into 1). These tests guard
 * against anyone "de-duplicating" these rows again — via a `distinct`
 * validation rule, a unique index on (bundle_id, service_id), or a cleanup
 * script — because the package price SUMS the rows, so dropping one changes
 * the price and drops a session.
 */
class PackageQuantityAsRowsTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
    }

    public function test_package_persists_repeated_service_as_one_row_per_session(): void
    {
        $service = Services::factory()->create(['account_id' => 1]);

        // A "6-session" package of ONE service = that service listed 6 times.
        // Mirrors the SPA, which expands a per-service qty into N service ids.
        $bundle = app(BundleService::class)->createBundle([
            'name'          => '6-session package of one service',
            'price'         => 6000,
            'service_id'    => array_fill(0, 6, $service->id),
            'service_price' => array_fill(0, 6, 1000),
        ]);

        $rows = BundleHasServices::where('bundle_id', $bundle->id)
            ->where('service_id', $service->id)
            ->count();

        $this->assertSame(
            6,
            $rows,
            'A 6-session package must persist 6 rows — quantity IS the row count. '
            . 'Repeated (bundle_id, service_id) rows are legitimate, not duplicates; never dedupe them.'
        );
    }

    public function test_store_bundle_request_does_not_forbid_duplicate_services(): void
    {
        // The request must NOT carry a `distinct` rule on service_id, or
        // "N sessions of one service" inside a package becomes impossible.
        $rules = (new StoreBundleRequest())->rules();

        $this->assertIsString($rules['service_id']);
        $this->assertStringNotContainsString(
            'distinct',
            $rules['service_id'],
            'service_id must allow duplicates — package quantity is stored as repeated rows.'
        );
    }
}
