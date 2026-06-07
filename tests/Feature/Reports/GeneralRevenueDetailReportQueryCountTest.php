<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Models\Locations;
use App\Services\Reports\Revenue\GeneralRevenueDetailReport;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * N+1 guard for GeneralRevenueDetailReport::generate().
 *
 * The report iterates a list of branches. It used to call Locations::find()
 * per branch and then touch the unloaded `city` + `region` relations per
 * branch (one query for the find, one each for city/region accessors). The
 * fix eager-loads every branch with city + region in a fixed number of
 * queries before the loop.
 *
 * Pin: the per-branch query count must NOT grow with the number of branches.
 * Revert the eager-load and the count climbs by ~3 per extra branch.
 */
class GeneralRevenueDetailReportQueryCountTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
    }

    private function countQueriesFor(array $locationIds): int
    {
        $report = new GeneralRevenueDetailReport();

        DB::flushQueryLog();
        DB::enableQueryLog();
        // A window with no matching advances still exercises the per-branch
        // location + city + region access — exactly the N+1 path under test.
        $report->generate('2026-01-01', '2026-01-31', $locationIds, 1, 'all');
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    public function test_branch_loop_does_not_run_per_branch_location_queries(): void
    {
        $one = Locations::factory()->create(['account_id' => 1]);
        $baseline = $this->countQueriesFor([$one->id]);

        $two = Locations::factory()->create(['account_id' => 1]);
        $three = Locations::factory()->create(['account_id' => 1]);
        $four = Locations::factory()->create(['account_id' => 1]);
        $withMore = $this->countQueriesFor([
            $one->id, $two->id, $three->id, $four->id,
        ]);

        // 4 branches add at most the per-branch advances query (1 each).
        // Without the eager-load they would ALSO add a find() + city + region
        // query per extra branch — so the delta would be far larger than the
        // 3 extra advances queries for the 3 extra branches.
        $this->assertLessThanOrEqual(
            $baseline + 3,
            $withMore,
            "Branch loop query count grew from {$baseline} to {$withMore} for 3 extra branches — locations/city/region are being loaded per branch (N+1)."
        );
    }
}
