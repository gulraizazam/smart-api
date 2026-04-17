<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Models\PackageBundles;
use App\Models\Packages;
use App\Models\ServiceBundle;
use App\Models\ServiceBundlePriceHistory;
use App\Services\Service\ServiceService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Pins the behaviour of cascading a service price change onto every
 * ServiceBundle that references it.
 *
 * Contract:
 *   1. On service price change, every bundle for that service is re-priced
 *      as ROUND(new_service_price * sessions * (1 - discount/100)).
 *   2. Inactive bundles are also re-priced (single source of truth).
 *   3. A history row is written per affected bundle.
 *   4. Already-sold package_bundles snapshot values are untouched.
 *   5. A no-op service save (price unchanged) does not write history.
 */
class ServiceBundleCascadeTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private ServiceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();

        $this->service = app(ServiceService::class);
    }

    public function test_price_change_cascades_to_all_bundles_and_writes_history(): void
    {
        $accountId = (int) Auth::user()->account_id;
        $userId = (int) Auth::id();

        $created = $this->service->createService([
            'name' => 'Cascade Service',
            'duration' => 30,
            'price' => 1000,
            'parent_id' => null,
            'end_node' => 1,
            'active' => 1,
            'tax_treatment_type_id' => 1,
        ], $accountId, $userId);

        $bundleA = ServiceBundle::create([
            'service_id' => $created->id,
            'sessions' => 6,
            'discount_percentage' => 10,
            'price' => 5400,
            'sort_number' => 0,
            'active' => 1,
            'account_id' => $accountId,
            'created_by' => $userId,
        ]);

        $bundleB = ServiceBundle::create([
            'service_id' => $created->id,
            'sessions' => 10,
            'discount_percentage' => 20,
            'price' => 8000,
            'sort_number' => 1,
            'active' => 1,
            'account_id' => $accountId,
            'created_by' => $userId,
        ]);

        $this->service->updateService(
            $created->id,
            [
                'name' => 'Cascade Service',
                'duration' => 30,
                'price' => 2000,
                'parent_id' => null,
                'end_node' => 1,
                'active' => 1,
                'tax_treatment_type_id' => 1,
            ],
            $accountId,
            $userId,
        );

        // 2000 * 6 * 0.90 = 10800
        $this->assertEqualsWithDelta(10800.0, (float) $bundleA->fresh()->price, 0.01);
        // 2000 * 10 * 0.80 = 16000
        $this->assertEqualsWithDelta(16000.0, (float) $bundleB->fresh()->price, 0.01);

        $history = ServiceBundlePriceHistory::where('service_id', $created->id)->get();
        $this->assertCount(2, $history);
        $this->assertEqualsWithDelta(1000.0, (float) $history->first()->old_service_price, 0.01);
        $this->assertEqualsWithDelta(2000.0, (float) $history->first()->new_service_price, 0.01);
    }

    public function test_no_bundles_means_no_history_and_no_error(): void
    {
        $accountId = (int) Auth::user()->account_id;
        $userId = (int) Auth::id();

        $created = $this->service->createService([
            'name' => 'Lonely Service',
            'duration' => 30,
            'price' => 1000,
            'parent_id' => null,
            'end_node' => 1,
            'active' => 1,
            'tax_treatment_type_id' => 1,
        ], $accountId, $userId);

        $this->service->updateService(
            $created->id,
            [
                'name' => 'Lonely Service',
                'duration' => 30,
                'price' => 1500,
                'parent_id' => null,
                'end_node' => 1,
                'active' => 1,
                'tax_treatment_type_id' => 1,
            ],
            $accountId,
            $userId,
        );

        $this->assertSame(0, ServiceBundlePriceHistory::where('service_id', $created->id)->count());
    }

    public function test_price_unchanged_skips_cascade(): void
    {
        $accountId = (int) Auth::user()->account_id;
        $userId = (int) Auth::id();

        $created = $this->service->createService([
            'name' => 'Stable Service',
            'duration' => 30,
            'price' => 1000,
            'parent_id' => null,
            'end_node' => 1,
            'active' => 1,
            'tax_treatment_type_id' => 1,
        ], $accountId, $userId);

        ServiceBundle::create([
            'service_id' => $created->id,
            'sessions' => 6,
            'discount_percentage' => 10,
            'price' => 5400,
            'sort_number' => 0,
            'active' => 1,
            'account_id' => $accountId,
            'created_by' => $userId,
        ]);

        $this->service->updateService(
            $created->id,
            [
                'name' => 'Stable Service Renamed',
                'duration' => 30,
                'price' => 1000,
                'parent_id' => null,
                'end_node' => 1,
                'active' => 1,
                'tax_treatment_type_id' => 1,
            ],
            $accountId,
            $userId,
        );

        $this->assertSame(0, ServiceBundlePriceHistory::where('service_id', $created->id)->count());
    }

    public function test_inactive_bundle_is_still_repriced(): void
    {
        $accountId = (int) Auth::user()->account_id;
        $userId = (int) Auth::id();

        $created = $this->service->createService([
            'name' => 'Inactive Bundle Service',
            'duration' => 30,
            'price' => 1000,
            'parent_id' => null,
            'end_node' => 1,
            'active' => 1,
            'tax_treatment_type_id' => 1,
        ], $accountId, $userId);

        $bundle = ServiceBundle::create([
            'service_id' => $created->id,
            'sessions' => 4,
            'discount_percentage' => 25,
            'price' => 3000,
            'sort_number' => 0,
            'active' => 0,
            'account_id' => $accountId,
            'created_by' => $userId,
        ]);

        $this->service->updateService(
            $created->id,
            [
                'name' => 'Inactive Bundle Service',
                'duration' => 30,
                'price' => 2000,
                'parent_id' => null,
                'end_node' => 1,
                'active' => 1,
                'tax_treatment_type_id' => 1,
            ],
            $accountId,
            $userId,
        );

        // 2000 * 4 * 0.75 = 6000
        $this->assertEqualsWithDelta(6000.0, (float) $bundle->fresh()->price, 0.01);
    }

    public function test_sold_package_bundles_retain_their_snapshotted_price(): void
    {
        $accountId = (int) Auth::user()->account_id;
        $userId = (int) Auth::id();

        $created = $this->service->createService([
            'name' => 'Snapshot Service',
            'duration' => 30,
            'price' => 1000,
            'parent_id' => null,
            'end_node' => 1,
            'active' => 1,
            'tax_treatment_type_id' => 1,
        ], $accountId, $userId);

        $bundle = ServiceBundle::create([
            'service_id' => $created->id,
            'sessions' => 6,
            'discount_percentage' => 10,
            'price' => 5400,
            'sort_number' => 0,
            'active' => 1,
            'account_id' => $accountId,
            'created_by' => $userId,
        ]);

        $package = Packages::create([
            'random_id' => (string) Str::uuid(),
            'account_id' => $accountId,
            'total_price' => 5400,
            'active' => 1,
            'sessioncount' => 6,
        ]);

        $packageBundle = PackageBundles::create([
            'package_id' => $package->id,
            'bundle_id' => $bundle->id,
            'source_type' => 'service_bundle',
            'qty' => 6,
            'service_price' => 5400,
            'net_amount' => 5400,
            'discount_name' => '-',
            'discount_type' => '-',
            'discount_price' => 0,
            'tax_percentage' => 0,
            'tax_price' => 0,
            'tax_including_price' => 5400,
            'tax_exclusive_net_amount' => 5400,
        ]);

        $this->service->updateService(
            $created->id,
            [
                'name' => 'Snapshot Service',
                'duration' => 30,
                'price' => 3000,
                'parent_id' => null,
                'end_node' => 1,
                'active' => 1,
                'tax_treatment_type_id' => 1,
            ],
            $accountId,
            $userId,
        );

        $this->assertEqualsWithDelta(5400.0, (float) $packageBundle->fresh()->service_price, 0.01);
        $this->assertEqualsWithDelta(5400.0, (float) $packageBundle->fresh()->net_amount, 0.01);
        // 3000 * 6 * 0.90 = 16200 — template re-priced
        $this->assertEqualsWithDelta(16200.0, (float) $bundle->fresh()->price, 0.01);
    }

    public function test_preview_returns_affected_bundles_without_persisting_change(): void
    {
        $accountId = (int) Auth::user()->account_id;
        $userId = (int) Auth::id();

        $created = $this->service->createService([
            'name' => 'Preview Service',
            'duration' => 30,
            'price' => 1000,
            'parent_id' => null,
            'end_node' => 1,
            'active' => 1,
            'tax_treatment_type_id' => 1,
        ], $accountId, $userId);

        $bundle = ServiceBundle::create([
            'service_id' => $created->id,
            'sessions' => 6,
            'discount_percentage' => 10,
            'price' => 5400,
            'sort_number' => 0,
            'active' => 1,
            'account_id' => $accountId,
            'created_by' => $userId,
        ]);

        $preview = $this->service->previewServiceBundleImpact($created->id, 2000.0, $accountId);

        $this->assertCount(1, $preview);
        $this->assertSame($bundle->id, $preview[0]['service_bundle_id']);
        $this->assertEqualsWithDelta(5400.0, $preview[0]['old_bundle_price'], 0.01);
        $this->assertEqualsWithDelta(10800.0, $preview[0]['new_bundle_price'], 0.01);

        $this->assertEqualsWithDelta(5400.0, (float) $bundle->fresh()->price, 0.01);
        $this->assertEqualsWithDelta(1000.0, (float) $created->fresh()->price, 0.01);
    }
}
