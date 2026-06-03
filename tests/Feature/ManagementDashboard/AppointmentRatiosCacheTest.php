<?php

declare(strict_types=1);

namespace Tests\Feature\ManagementDashboard;

use App\Services\Dashboard\Metrics\AppointmentsMetric;
use App\Services\Dashboard\ValueObjects\DateRange;
use App\Services\Dashboard\ValueObjects\MetricScope;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Pins the Overview perf optimisation: AppointmentsMetric::ratios() — the
 * Stats card's heaviest computation, called TWICE per request (current +
 * prior period) — is cached per scope+range, so the second pass and tab
 * re-entries are served from cache instead of recomputed.
 */
class AppointmentRatiosCacheTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        Cache::flush();
    }

    private function key(MetricScope $scope, DateRange $range): string
    {
        return 'mgmt_dash:appointment_ratios:'.$scope->cacheKey()
            .'|'.$range->startString().'..'.$range->endString();
    }

    public function test_ratios_caches_its_result_per_scope_and_range(): void
    {
        $metric = app(AppointmentsMetric::class);
        $scope = MetricScope::company(1);
        $range = DateRange::fromStrings('2026-05-01', '2026-05-31');
        $key = $this->key($scope, $range);

        $this->assertFalse(Cache::has($key), 'cache should be empty before the first call');

        $first = $metric->ratios($scope, $range);

        $this->assertTrue(Cache::has($key), 'ratios() must populate the cache');
        $this->assertSame($first, Cache::get($key), 'cached value must equal the returned value');
        // Second call returns the identical cached payload (no recompute).
        $this->assertSame($first, $metric->ratios($scope, $range));
    }

    public function test_deny_all_scope_short_circuits_before_the_cache(): void
    {
        $metric = app(AppointmentsMetric::class);
        $scope = MetricScope::branches(1, []); // empty branch list = deny-all
        $range = DateRange::fromStrings('2026-05-01', '2026-05-31');

        $result = $metric->ratios($scope, $range);

        $this->assertSame(0.0, $result['sales']);
        $this->assertSame(['arrived' => 0, 'total' => 0], $result['consultations']);
        $this->assertFalse(Cache::has($this->key($scope, $range)), 'deny-all must not touch the cache');
    }
}
