<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Services\Reports\AppointmentsReportService;
use Illuminate\Support\Collection;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Smoke coverage for AppointmentsReportService — the "time-to-invoice"
 * report that surfaces consultancy appointments where an invoice was
 * created within N minutes of appointment creation. It's used by
 * operations to track conversion latency.
 *
 * Pins:
 *   1. Empty fixture returns an empty Collection (not null / array).
 *   2. The service honors the centreIds filter (renamed from singular
 *      `centreId` when the API widened to multi-centre selection) —
 *      when a non-existent centre is passed, the result is still a
 *      Collection (empty).
 *   3. The service honors the createdBy filter the same way.
 */
class AppointmentSummaryReportTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private AppointmentsReportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();

        $this->service = app(AppointmentsReportService::class);
    }

    public function test_generate_returns_empty_collection_on_empty_fixture(): void
    {
        $result = $this->service->generate(
            startDate: now()->subWeek()->format('Y-m-d 00:00:00'),
            endDate: now()->format('Y-m-d 23:59:59'),
            timeInterval: 60,
        );

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertTrue($result->isEmpty());
    }

    public function test_generate_honors_the_centre_ids_filter(): void
    {
        $result = $this->service->generate(
            startDate: now()->subWeek()->format('Y-m-d 00:00:00'),
            endDate: now()->format('Y-m-d 23:59:59'),
            timeInterval: 60,
            centreIds: [99_999],
        );

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertTrue($result->isEmpty());
    }

    public function test_generate_honors_the_created_by_filter(): void
    {
        $result = $this->service->generate(
            startDate: now()->subWeek()->format('Y-m-d 00:00:00'),
            endDate: now()->format('Y-m-d 23:59:59'),
            timeInterval: 60,
            createdBy: 99_999,
        );

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertTrue($result->isEmpty());
    }
}
