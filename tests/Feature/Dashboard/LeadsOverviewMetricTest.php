<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use App\Models\LeadStatuses;
use App\Models\Leads;
use App\Services\Dashboard\Metrics\LeadsOverviewMetric;
use App\Services\Dashboard\Support\LeadStatusResolver;
use App\Services\Dashboard\ValueObjects\DateRange;
use App\Services\Dashboard\ValueObjects\MetricScope;
use Carbon\CarbonImmutable;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Pins the LeadsOverviewMetric — the KPI row at the top of the Marketing tab.
 *
 * The invariants that MUST hold and would silently corrupt every downstream
 * panel if regressed:
 *
 *   1. Tenant isolation — a scope on account A never sees rows from account B.
 *   2. Range boundary — a lead created 1s before the window starts is out;
 *      1s after is in.
 *   3. Converted count is driven by `is_converted` flags on lead_statuses,
 *      not hardcoded IDs (that used to be a lurking bug fixed by the
 *      shared LeadStatusResolver).
 */
class LeadsOverviewMetricTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
    }

    public function test_counts_only_leads_in_the_current_tenant(): void
    {
        [$openStatus] = $this->seedStatuses(accountId: 1);
        $this->seedStatuses(accountId: 999); // foreign tenant's statuses

        $this->makeLead(accountId: 1, statusId: $openStatus->id, at: '2026-03-10');
        $this->makeLead(accountId: 999, statusId: null, at: '2026-03-10'); // foreign

        $result = $this->metric()->compute(
            MetricScope::company(1),
            DateRange::fromStrings('2026-03-01', '2026-03-31'),
        );

        $this->assertSame(1, $result['total']);
    }

    public function test_range_boundaries_are_inclusive_at_day_level(): void
    {
        [$openStatus] = $this->seedStatuses(accountId: 1);

        $this->makeLead(accountId: 1, statusId: $openStatus->id, at: '2026-03-01 00:00:00');   // just in
        $this->makeLead(accountId: 1, statusId: $openStatus->id, at: '2026-03-31 23:59:59');   // just in
        $this->makeLead(accountId: 1, statusId: $openStatus->id, at: '2026-02-28 23:59:59');   // out (day before)
        $this->makeLead(accountId: 1, statusId: $openStatus->id, at: '2026-04-01 00:00:00');   // out (day after)

        $result = $this->metric()->compute(
            MetricScope::company(1),
            DateRange::fromStrings('2026-03-01', '2026-03-31'),
        );

        $this->assertSame(2, $result['total']);
    }

    public function test_converted_count_uses_is_converted_flag_not_hardcoded_ids(): void
    {
        [$open, $converted] = $this->seedStatuses(accountId: 1);

        $this->makeLead(accountId: 1, statusId: $open->id, at: '2026-03-10');
        $this->makeLead(accountId: 1, statusId: $converted->id, at: '2026-03-10');
        $this->makeLead(accountId: 1, statusId: $converted->id, at: '2026-03-12');

        $result = $this->metric()->compute(
            MetricScope::company(1),
            DateRange::fromStrings('2026-03-01', '2026-03-31'),
        );

        $this->assertSame(3, $result['total']);
        $this->assertSame(2, $result['converted']);
        $this->assertGreaterThan(66.0, $result['converted_pct']); // ~66.7%
    }

    public function test_deny_all_scope_returns_zeroes(): void
    {
        [$open] = $this->seedStatuses(accountId: 1);
        $this->makeLead(accountId: 1, statusId: $open->id, at: '2026-03-10');

        $scope = MetricScope::branches(1, []);
        $result = $this->metric()->compute(
            $scope,
            DateRange::fromStrings('2026-03-01', '2026-03-31'),
        );

        $this->assertSame(0, $result['total']);
        $this->assertSame(0, $result['converted']);
        $this->assertSame(0.0, $result['revenue']);
    }

    // ------------------------------------------------------------------

    private function metric(): LeadsOverviewMetric
    {
        return new LeadsOverviewMetric(new LeadStatusResolver());
    }

    /**
     * @return array{LeadStatuses, LeadStatuses}  [open, converted]
     */
    private function seedStatuses(int $accountId): array
    {
        $open = LeadStatuses::create([
            'name' => 'Open',
            'account_id' => $accountId,
            'is_junk' => 0,
            'is_arrived' => 0,
            'is_converted' => 0,
            'is_booked' => 0,
            'is_default' => 1,
        ]);
        $converted = LeadStatuses::create([
            'name' => 'Converted',
            'account_id' => $accountId,
            'is_junk' => 0,
            'is_arrived' => 1,
            'is_converted' => 1,
            'is_booked' => 1,
            'is_default' => 0,
        ]);

        return [$open, $converted];
    }

    private function makeLead(int $accountId, ?int $statusId, string $at): Leads
    {
        return Leads::create([
            'account_id' => $accountId,
            'name' => 'Test',
            'phone' => '+92300'.random_int(1000000, 9999999),
            'gender' => 1,
            'lead_status_id' => $statusId,
            'active' => 1,
            'created_at' => CarbonImmutable::parse($at),
            'updated_at' => CarbonImmutable::parse($at),
        ]);
    }
}
