<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\Services;
use App\Services\Service\ServiceService;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Service-layer invariants pinned during the v2 SPA port.
 *
 *   1. `createService()` must NOT store `parent_id = 0` — the
 *      `fk_services_parent_id` self-ref FK rejects 0 since there is no
 *      `services.id = 0` row. Legacy form convention is "0 = make this a
 *      parent category"; `ServiceHelper::prepareServiceData()` now
 *      coerces 0/empty to NULL at the persistence seam.
 *
 *   2. Same coercion on `updateService()` — promoting a child to a parent
 *      by clearing its parent must land as NULL, not 0.
 *
 *   3. The merge-conflict resolution introduced three live methods:
 *      `cascadeServiceBundlePricing`, `previewServiceBundleImpact`,
 *      `computeBundlePrice`. Smoke-check that the public method is
 *      reachable and returns an empty affected list when the price
 *      hasn't actually changed (guards against a price-cascade firing
 *      on unrelated edits).
 *
 *   4. Deactivating a parent cascades to its children (tested through
 *      the `isParent` accessor which now backs the cascade branch).
 */
class ServiceServiceTest extends TestCase
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

    public function test_create_with_parent_id_zero_stores_null(): void
    {
        $accountId = (int) Auth::user()->account_id;
        $userId = (int) Auth::id();

        $parent = $this->service->createService([
            'name' => 'Body Contouring',
            'parent_id' => 0,
            'color' => '#ff8800',
        ], $accountId, $userId);

        $this->assertNotNull($parent->id);
        $this->assertNull(
            $parent->fresh()->parent_id,
            'parent_id must be NULL when creating a parent — 0 violates fk_services_parent_id.',
        );
        $this->assertTrue($parent->fresh()->isParent);
    }

    public function test_create_with_empty_parent_id_stores_null(): void
    {
        $accountId = (int) Auth::user()->account_id;
        $userId = (int) Auth::id();

        $parent = $this->service->createService([
            'name' => 'Facials',
            'parent_id' => '',
            'color' => '#112233',
        ], $accountId, $userId);

        $this->assertNull($parent->fresh()->parent_id);
    }

    public function test_create_with_real_parent_id_persists_it(): void
    {
        $accountId = (int) Auth::user()->account_id;
        $userId = (int) Auth::id();

        $parent = $this->service->createService([
            'name' => 'Body Contouring',
            'parent_id' => 0,
            'color' => '#ff8800',
        ], $accountId, $userId);

        $child = $this->service->createService([
            'name' => 'Ultrashape Abdomen',
            'parent_id' => $parent->id,
            'duration' => '01:00',
            'price' => 15995,
            'color' => '#ff8800',
            'tax_treatment_type_id' => 3,
            'end_node' => 1,
        ], $accountId, $userId);

        $this->assertSame($parent->id, (int) $child->fresh()->parent_id);
        $this->assertFalse($child->fresh()->isParent);
    }

    public function test_update_to_no_parent_clears_to_null(): void
    {
        $accountId = (int) Auth::user()->account_id;
        $userId = (int) Auth::id();

        $parent = $this->service->createService(
            ['name' => 'Top', 'parent_id' => 0, 'color' => '#000000'],
            $accountId,
            $userId,
        );
        $child = $this->service->createService([
            'name' => 'Child',
            'parent_id' => $parent->id,
            'duration' => '00:30',
            'price' => 100,
            'color' => '#000000',
            'tax_treatment_type_id' => 3,
            'end_node' => 1,
        ], $accountId, $userId);

        $updated = $this->service->updateService($child->id, [
            'name' => $child->name,
            'parent_id' => '',
            'duration' => $child->duration,
            'price' => $child->price,
            'color' => $child->color,
            'tax_treatment_type_id' => 3,
            'end_node' => 1,
        ], $accountId, $userId);

        $this->assertNull($updated->fresh()->parent_id);
    }

    public function test_preview_bundle_impact_returns_empty_when_price_unchanged(): void
    {
        $accountId = (int) Auth::user()->account_id;
        $userId = (int) Auth::id();

        $parent = $this->service->createService(
            ['name' => 'Top', 'parent_id' => 0, 'color' => '#000000'],
            $accountId,
            $userId,
        );
        $child = $this->service->createService([
            'name' => 'Child',
            'parent_id' => $parent->id,
            'duration' => '00:30',
            'price' => 500,
            'color' => '#000000',
            'tax_treatment_type_id' => 3,
            'end_node' => 1,
        ], $accountId, $userId);

        $affected = $this->service->previewServiceBundleImpact($child->id, 500.0, $accountId);

        $this->assertSame([], $affected, 'Preview must return [] when new_price equals current price.');
    }

    public function test_deactivate_parent_cascades_to_children(): void
    {
        $accountId = (int) Auth::user()->account_id;
        $userId = (int) Auth::id();

        $parent = $this->service->createService(
            ['name' => 'Top', 'parent_id' => 0, 'color' => '#000000'],
            $accountId,
            $userId,
        );
        $child = $this->service->createService([
            'name' => 'Child',
            'parent_id' => $parent->id,
            'duration' => '00:30',
            'price' => 100,
            'color' => '#000000',
            'tax_treatment_type_id' => 3,
            'end_node' => 1,
        ], $accountId, $userId);

        $this->service->deactivateService($parent->id, $accountId);

        $this->assertSame(0, (int) $parent->fresh()->active);
        $this->assertSame(
            0,
            (int) $child->fresh()->active,
            'Deactivating a parent must cascade to children — regression guard for the isParent-vs-===-0 branch.',
        );
    }

    public function test_isparent_accessor_treats_null_and_zero_the_same(): void
    {
        $accountId = (int) Auth::user()->account_id;
        $userId = (int) Auth::id();

        $parent = $this->service->createService(
            ['name' => 'Via Service', 'parent_id' => 0, 'color' => '#000'],
            $accountId,
            $userId,
        );
        $this->assertTrue($parent->fresh()->isParent);

        // A legacy row inserted with parent_id=0 directly (pre-migration)
        // must also be recognised as a parent by the accessor.
        $legacy = new Services([
            'name' => 'Legacy',
            'color' => '#000',
        ]);
        $legacy->account_id = $accountId;
        $legacy->parent_id = 0;
        $legacy->active = 1;
        $legacy->save();

        $this->assertTrue($legacy->fresh()->isParent);
    }
}
