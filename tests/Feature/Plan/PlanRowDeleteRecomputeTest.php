<?php

declare(strict_types=1);

namespace Tests\Feature\Plan;

use App\Models\Packages;
use App\Services\Plan\PlanService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Single-row delete recompute. Deleting ONE service row must drop that row
 * (+ its package_services children) and recompute the plan's total_price
 * from the surviving rows' tax_including_price. Only the cascade-delete path
 * was pinned before (CascadeDeleteGroupTest); single delete is the everyday
 * path and was unpinned — a recompute regression would silently leave the
 * plan total out of step with its rows.
 */
class PlanRowDeleteRecomputeTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    public function test_single_row_delete_recomputes_plan_total_from_surviving_rows(): void
    {
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
        $service = app(PlanService::class);

        $randomId = 'DEL-RID-'.uniqid();
        $plan = Packages::factory()->create([
            'account_id' => 1, 'location_id' => $this->defaultLocation->id,
            'random_id' => $randomId, 'plan_type' => 'plan', 'total_price' => 20000,
        ]);

        $rowIds = [];
        foreach ([0, 1] as $i) {
            $bundleId = (int) DB::table('package_bundles')->insertGetId([
                'account_id' => 1, 'package_id' => $plan->id, 'bundle_id' => 1, 'qty' => 1,
                'source_type' => 'service', 'random_id' => $randomId, 'is_allocate' => 1,
                'service_price' => 10000, 'net_amount' => 10000, 'tax_including_price' => 10000,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('package_services')->insert([
                'package_id' => $plan->id, 'package_bundle_id' => $bundleId, 'random_id' => $randomId,
                'price' => 10000, 'tax_including_price' => 10000, 'is_consumed' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $rowIds[] = $bundleId;
        }

        $result = $service->deletePackageService([
            'id' => $rowIds[0],
            'random_id' => $randomId,
            'update_status' => 1,
        ]);

        $this->assertTrue($result['success']);
        // Deleted row + its children gone.
        $this->assertDatabaseMissing('package_bundles', ['id' => $rowIds[0]]);
        $this->assertSame(0, DB::table('package_services')->where('package_bundle_id', $rowIds[0])->count());
        // Surviving row intact.
        $this->assertDatabaseHas('package_bundles', ['id' => $rowIds[1]]);
        // Total recomputed from the one surviving row (10000), not left at 20000.
        $this->assertSame(10000.0, (float) $plan->fresh()->total_price,
            'Plan total must recompute to the surviving rows after a single delete.');
    }
}
