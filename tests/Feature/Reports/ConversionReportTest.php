<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Services\Reports\Revenue\ConversionReport;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Smoke coverage for the conversion report — which tracks how many
 * "arrived" consultation appointments converted to a paid package
 * within the requested date window. The service is legacy code
 * extracted from `Finanaces::conversion_report()` and relies on
 * several lookups (AppointmentTypes slug='consultancy', status
 * 'arrived', PackageAdvances cash_flow='in') all wired via the
 * UsesFinancialFixtures trait.
 *
 * Pins:
 *   1. Empty fixture returns the expected top-level keys.
 *   2. `report_data` / `locationData` are empty collections when
 *      there is no conversion activity.
 *   3. `start_date` / `end_date` round-trip through the result.
 */
class ConversionReportTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private ConversionReport $report;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();

        $this->report = app(ConversionReport::class);
    }

    public function test_generate_returns_expected_shape_on_empty_fixture(): void
    {
        $result = $this->report->generate(
            startDate: now()->subWeek()->format('Y-m-d'),
            endDate: now()->format('Y-m-d'),
            requestData: [],
            accountId: (int) Auth::user()->account_id,
        );

        $this->assertArrayHasKey('report_data', $result);
        $this->assertArrayHasKey('locationData', $result);
        $this->assertArrayHasKey('start_date', $result);
        $this->assertArrayHasKey('end_date', $result);
    }

    public function test_generate_yields_empty_report_data_when_no_appointments(): void
    {
        $result = $this->report->generate(
            startDate: now()->subWeek()->format('Y-m-d'),
            endDate: now()->format('Y-m-d'),
            requestData: [],
            accountId: (int) Auth::user()->account_id,
        );

        $this->assertSame([], $result['report_data']);
        $this->assertSame([], $result['locationData']);
    }

    public function test_generate_preserves_the_input_date_range(): void
    {
        $start = '2026-01-01';
        $end = '2026-01-31';

        $result = $this->report->generate(
            startDate: $start,
            endDate: $end,
            requestData: [],
            accountId: (int) Auth::user()->account_id,
        );

        $this->assertSame($start, $result['start_date']);
        $this->assertSame($end, $result['end_date']);
    }
}
