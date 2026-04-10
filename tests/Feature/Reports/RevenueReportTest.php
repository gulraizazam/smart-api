<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Services\Reports\Revenue\GeneralRevenueDetailReport;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Smoke coverage for the detail-level revenue report. The service
 * iterates each requested location, eager-loads its PackageAdvances,
 * and builds a per-location balance sheet keyed by location id.
 *
 * Pins:
 *   1. Passing a location id that has no advances yields an entry
 *      with `revenue_data => []`.
 *   2. Passing a non-existent location is silently skipped.
 *   3. Gender filter "all" is accepted (the default path).
 */
class RevenueReportTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private GeneralRevenueDetailReport $report;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();

        $this->report = app(GeneralRevenueDetailReport::class);
    }

    public function test_generate_returns_empty_revenue_data_for_location_with_no_advances(): void
    {
        $locationId = $this->seedDefaultLocation()->id;
        $loc = \App\Models\Locations::find($locationId);

        $result = $this->report->generate(
            startDate: now()->subDay()->format('Y-m-d'),
            endDate: now()->format('Y-m-d'),
            locationIds: [$locationId],
            accountId: (int) Auth::user()->account_id,
        );

        // GeneralRevenueDetailReport wraps the per-location map in
        // a `report_data` key alongside the roll-up totals — match
        // the `buildResult()` contract.
        $this->assertArrayHasKey('report_data', $result);
        $this->assertArrayHasKey($locationId, $result['report_data']);
        $this->assertSame([], $result['report_data'][$locationId]['revenue_data']);
        $this->assertSame($loc->name, $result['report_data'][$locationId]['name']);
    }

    public function test_generate_silently_skips_nonexistent_location_ids(): void
    {
        $result = $this->report->generate(
            startDate: now()->subDay()->format('Y-m-d'),
            endDate: now()->format('Y-m-d'),
            locationIds: [99_999],
            accountId: (int) Auth::user()->account_id,
        );

        $this->assertArrayHasKey('report_data', $result);
        $this->assertArrayNotHasKey(99_999, $result['report_data']);
    }

    public function test_generate_accepts_gender_filter_all(): void
    {
        $result = $this->report->generate(
            startDate: now()->subDay()->format('Y-m-d'),
            endDate: now()->format('Y-m-d'),
            locationIds: [$this->seedDefaultLocation()->id],
            accountId: (int) Auth::user()->account_id,
            genderId: 'all',
        );

        $this->assertIsArray($result);
    }
}
