<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Services\Reports\Enums\OperationsReportType;
use App\Services\Reports\OperationsReportService;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Smoke coverage for the operations report service — the match
 * dispatcher that routes an enum-typed report request to one of
 * three underlying generators (DAR, Walking, Agent).
 *
 * Pins:
 *   1. Each enum variant produces a result shape containing the
 *      `reportData` / `locationData` / `start_date` / `end_date`
 *      keys from the shared Operations reports contract.
 *   2. `getReportInstance()` returns a concrete class matching the
 *      requested enum — used by the controller to wire up the Excel
 *      export side-door.
 */
class OperationsReportTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private OperationsReportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();

        $this->service = app(OperationsReportService::class);
    }

    /**
     * All three underlying generators (DarReport / WalkingReport /
     * AgentReport) wrap legacy `Operations::*` functions that read
     * date_range out of the requestData and expect a
     * "YYYY-MM-DD - YYYY-MM-DD" string (date_range_picker format).
     */
    private function requestData(): array
    {
        $start = now()->subWeek()->format('Y-m-d');
        $end = now()->format('Y-m-d');

        return [
            'location_id' => null,
            'date_range' => "{$start} - {$end}",
        ];
    }

    public function test_dar_report_returns_expected_shape(): void
    {
        $result = $this->service->generate(
            reportType: OperationsReportType::DarReport,
            requestData: $this->requestData(),
            startDate: now()->subWeek()->format('Y-m-d'),
            endDate: now()->format('Y-m-d'),
        );

        $this->assertArrayHasKey('reportData', $result);
        $this->assertArrayHasKey('locationData', $result);
        $this->assertArrayHasKey('start_date', $result);
        $this->assertArrayHasKey('end_date', $result);
    }

    public function test_walking_report_returns_expected_shape(): void
    {
        $result = $this->service->generate(
            reportType: OperationsReportType::WalkingReport,
            requestData: $this->requestData(),
            startDate: now()->subWeek()->format('Y-m-d'),
            endDate: now()->format('Y-m-d'),
        );

        $this->assertArrayHasKey('reportData', $result);
        $this->assertArrayHasKey('locationData', $result);
    }

    public function test_agent_report_returns_expected_shape(): void
    {
        $result = $this->service->generate(
            reportType: OperationsReportType::AgentReport,
            requestData: $this->requestData(),
            startDate: now()->subWeek()->format('Y-m-d'),
            endDate: now()->format('Y-m-d'),
        );

        $this->assertArrayHasKey('reportData', $result);
        $this->assertArrayHasKey('locationData', $result);
    }

    public function test_get_report_instance_returns_the_matching_concrete(): void
    {
        $this->assertInstanceOf(
            \App\Services\Reports\Operations\DarReport::class,
            $this->service->getReportInstance(OperationsReportType::DarReport),
        );

        $this->assertInstanceOf(
            \App\Services\Reports\Operations\WalkingReport::class,
            $this->service->getReportInstance(OperationsReportType::WalkingReport),
        );

        $this->assertInstanceOf(
            \App\Services\Reports\Operations\AgentReport::class,
            $this->service->getReportInstance(OperationsReportType::AgentReport),
        );
    }
}
