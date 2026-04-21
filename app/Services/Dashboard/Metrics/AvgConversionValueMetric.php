<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Metrics;

use App\Services\Conversion\ConversionService;
use App\Services\Dashboard\Contracts\Metric;
use App\Services\Dashboard\Support\BranchResolver;
use App\Services\Dashboard\Support\DoctorResolver;
use App\Services\Dashboard\ValueObjects\DateRange;
use App\Services\Dashboard\ValueObjects\MetricScope;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * 12-month Average Conversion Value (ACV) series — scope-aware.
 *
 * Semantics match the Conversion Report: per-month ACV = validated-conversion
 * spend / validated-conversion count, where a "conversion" is defined by the
 * same 6-step chain (arrived consultation → invoice → service-after-invoice
 * → first payment on/after invoice → first payment in month → net spend
 * through end of that month).
 *
 * Performance: delegates to ConversionService::monthlySeriesForScope, which
 * runs one bulk fetch for the whole 12-month window and then buckets
 * validated conversions in PHP — avoids the Nx-month × M-doctor fan-out
 * that blew past the 30s script timeout.
 *
 * Range arg is ignored (widget is always trailing 12 months). Cached 5 min
 * per scope; invalidates with the rest of the Overview cache on filter
 * changes via the scope-keyed cache key.
 *
 * Return shape:
 *   [
 *     'series'           => list<array{month: string, acv: float, spend: float, converted: int}>,
 *     'latest'           => array|null,
 *     'prior3_avg'       => float | null,
 *     'prior3_delta_pct' => float | null,
 *   ]
 */
final class AvgConversionValueMetric implements Metric
{
    private const CACHE_TTL = 300;

    public function __construct(
        private readonly ConversionService $conversionService,
        private readonly DoctorResolver $doctors,
        private readonly BranchResolver $branches,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function compute(MetricScope $scope, DateRange $range): array
    {
        if ($scope->isDenyAll()) {
            return $this->empty();
        }

        $cacheKey = 'mgmt_dash:avg_conversion_value:'.$scope->cacheKey();

        return Cache::remember($cacheKey, self::CACHE_TTL, fn () => $this->build($scope));
    }

    /**
     * @return array<string, mixed>
     */
    private function build(MetricScope $scope): array
    {
        $end = CarbonImmutable::now()->endOfMonth();
        $start = $end->subMonths(11)->startOfMonth();

        $doctorIds = $this->doctors->idsInScope($scope);
        $branchIds = $this->branches->idsInScope($scope);

        if ($doctorIds === [] || $branchIds === []) {
            return $this->emptySeries($start, $end);
        }

        // Chunk the 12-month window into 3-month passes. The single-pass
        // bulk-fetch over 12 months exhausts PHP's 128MB limit on real
        // account volumes (tens of thousands of package_advances rows).
        // Per-month loops hit the 30s timeout. 3 months per pass is the
        // sweet spot: ~4 bulk fetches, each comfortably under memory, total
        // wall time well under the timeout.
        $buckets = [];
        $chunkStart = $start;
        while ($chunkStart->lessThanOrEqualTo($end)) {
            $chunkEnd = $chunkStart->addMonthsNoOverflow(2)->endOfMonth();
            if ($chunkEnd->greaterThan($end)) {
                $chunkEnd = $end;
            }

            $chunkBuckets = $this->conversionService->monthlySeriesForScope(
                $scope->accountId,
                $doctorIds,
                $branchIds,
                $chunkStart->format('Y-m-d'),
                $chunkEnd->format('Y-m-d'),
            );

            foreach ($chunkBuckets as $month => $row) {
                $buckets[$month] = $row;
            }

            $chunkStart = $chunkEnd->addDay()->startOfMonth();
        }

        $series = [];
        $cursor = $start;
        while ($cursor->lessThanOrEqualTo($end)) {
            $key = $cursor->format('Y-m');
            $row = $buckets[$key] ?? ['converted' => 0, 'spend' => 0.0];
            $converted = (int) $row['converted'];
            $spend = (float) $row['spend'];
            $acv = $converted > 0 ? round($spend / $converted, 2) : 0.0;

            $series[] = [
                'month' => $key,
                'acv' => $acv,
                'spend' => round($spend, 2),
                'converted' => $converted,
            ];
            $cursor = $cursor->addMonth();
        }

        $latest = end($series) ?: null;
        $prior3Avg = null;
        $prior3Delta = null;

        if ($latest !== null && count($series) >= 4) {
            $priorSlice = array_slice($series, -4, 3);
            $acvs = array_map(static fn (array $row): float => (float) $row['acv'], $priorSlice);
            $sum = array_sum($acvs);
            if ($sum > 0) {
                $prior3Avg = round($sum / 3, 2);
                if ($prior3Avg > 0) {
                    $prior3Delta = round((($latest['acv'] - $prior3Avg) / $prior3Avg) * 100, 1);
                }
            }
        }

        return [
            'series' => $series,
            'latest' => $latest ?: null,
            'prior3_avg' => $prior3Avg,
            'prior3_delta_pct' => $prior3Delta,
        ];
    }

    /**
     * Empty 12-month series (all zeros) for the current trailing window.
     * Used when there are no doctors/branches in scope so the frontend
     * still has a well-shaped payload to render.
     *
     * @return array<string, mixed>
     */
    private function emptySeries(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $series = [];
        $cursor = $start;
        while ($cursor->lessThanOrEqualTo($end)) {
            $series[] = ['month' => $cursor->format('Y-m'), 'acv' => 0.0, 'spend' => 0.0, 'converted' => 0];
            $cursor = $cursor->addMonth();
        }

        return ['series' => $series, 'latest' => end($series) ?: null, 'prior3_avg' => null, 'prior3_delta_pct' => null];
    }

    /**
     * @return array<string, mixed>
     */
    private function empty(): array
    {
        return [
            'series' => [],
            'latest' => null,
            'prior3_avg' => null,
            'prior3_delta_pct' => null,
        ];
    }
}
