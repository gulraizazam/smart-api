<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Services\Reports\Revenue\GeneralRevenueSummaryReport;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Smoke coverage for the "general revenue summary" report — the top
 * of the finance reporting stack. The real report is deeply stateful
 * (N+1 eliminated via eager loads across package_advances + gender
 * + payment mode joins) so we assert only the contract guarantees:
 *
 *  1. Empty DB → an array shaped with the expected aggregate keys.
 *  2. No crash when there are zero locations matching.
 *  3. Totals start at zero when there is no data.
 *
 * Deeper arithmetic is covered implicitly via Feature/CashFlow and
 * Feature/Packages tests that exercise the underlying PackageAdvances
 * rows the report aggregates.
 */
class FinanceReportTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private GeneralRevenueSummaryReport $report;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();

        $this->report = app(GeneralRevenueSummaryReport::class);
    }

    public function test_generate_returns_the_expected_aggregate_keys_on_empty_fixture(): void
    {
        $accountId = (int) Auth::user()->account_id;

        $result = $this->report->generate(
            startDate: now()->subDay()->format('Y-m-d'),
            endDate: now()->format('Y-m-d'),
            locationIds: null,
            regionId: null,
            accountId: $accountId,
        );

        foreach ([
            'report_data',
            'total_revenue_cash_in',
            'total_revenue_card_in',
            'total_revenue_bank_in',
            'total_refund',
            'total_revenue',
            'total_revenue_male_in',
            'total_revenue_female_in',
            'start_date',
            'end_date',
        ] as $key) {
            $this->assertArrayHasKey($key, $result, "Report result missing key: {$key}");
        }
    }

    public function test_generate_zeroes_all_totals_when_no_advances_are_present(): void
    {
        $accountId = (int) Auth::user()->account_id;

        $result = $this->report->generate(
            startDate: now()->subDay()->format('Y-m-d'),
            endDate: now()->format('Y-m-d'),
            locationIds: null,
            regionId: null,
            accountId: $accountId,
        );

        $this->assertSame(0, (int) $result['total_revenue_cash_in']);
        $this->assertSame(0, (int) $result['total_revenue_card_in']);
        $this->assertSame(0, (int) $result['total_revenue_bank_in']);
        $this->assertSame(0, (int) $result['total_refund']);
        $this->assertSame(0, (int) $result['total_revenue']);
    }

    public function test_generate_preserves_the_input_date_range_in_the_result(): void
    {
        $start = '2026-01-01';
        $end = '2026-01-31';

        $result = $this->report->generate(
            startDate: $start,
            endDate: $end,
            locationIds: null,
            regionId: null,
            accountId: (int) Auth::user()->account_id,
        );

        $this->assertSame($start, $result['start_date']);
        $this->assertSame($end, $result['end_date']);
    }
}
