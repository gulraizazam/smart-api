<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Metrics;

use App\Enums\ResourceType;
use App\Services\Dashboard\Contracts\Metric;
use App\Services\Dashboard\Support\DoctorResolver;
use App\Services\Dashboard\ValueObjects\DateRange;
use App\Services\Dashboard\ValueObjects\MetricScope;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Per-resource leaderboard for the People section. Doctor segment is
 * implemented; therapist / consultant follow the same pattern once role
 * semantics are defined.
 *
 * For each doctor in scope, computes revenue, conversion, avg client value,
 * upsell revenue, feedback score, patient return rate (same-doctor),
 * institutional return rate (any-doctor in network), patients seen, and a
 * trailing 6-month revenue trend series + slope. Company average included
 * for the outlier badges / reference lines.
 *
 * The whole result (including the 6× revenue computations per doctor for
 * the trend series) is wrapped in a 5-minute cache keyed on
 * (scope, range) — a single cache fill amortises the fan-out cost across
 * every doctor in scope.
 *
 * Return shape: { rows: list<resource_row>, company_avg: { ... } }
 */
final class ResourceLeaderboardMetric implements Metric
{
    private const CACHE_TTL = 300;

    /** Number of trailing monthly anchors used for the trend series. */
    private const TREND_MONTHS = 6;

    public function __construct(
        private readonly RevenueMetric $revenue,
        private readonly ConversionMetric $conversion,
        private readonly UpsellMetric $upsell,
        private readonly FeedbackMetric $feedback,
        private readonly PatientReturnMetric $patientReturn,
        private readonly AppointmentsMetric $appointments,
        private readonly DoctorResolver $doctors,
    ) {}

    /**
     * @return array{rows: list<array<string, mixed>>, company_avg: array<string, float>}
     */
    public function compute(MetricScope $scope, DateRange $range): array
    {
        $cacheKey = 'mgmt_dash:resource_leaderboard:v3:'
            .$scope->cacheKey()
            .'|'.$range->startString().'..'.$range->endString();

        return Cache::remember(
            $cacheKey,
            self::CACHE_TTL,
            fn (): array => $this->build($scope, $range),
        );
    }

    /**
     * @return array{rows: list<array<string, mixed>>, company_avg: array<string, float>}
     */
    private function build(MetricScope $scope, DateRange $range): array
    {
        $doctorIds = $this->doctors->idsInScope($scope);

        if ($doctorIds === []) {
            return ['rows' => [], 'company_avg' => $this->emptyAverages()];
        }

        $names = $this->doctors->names($doctorIds);
        $rows = [];

        // Pre-compute the 6 trailing monthly anchor ranges once — same for
        // every doctor in the loop, so we don't rebuild them per-iteration.
        $monthRanges = $this->trailingMonthRanges($range, self::TREND_MONTHS);

        foreach ($doctorIds as $doctorId) {
            $docScope = MetricScope::doctor($scope->accountId, $doctorId);

            $revenue = $this->revenue->compute($docScope, $range);
            $conversion = $this->conversion->compute($docScope, $range);
            $upsell = $this->upsell->compute($docScope, $range);
            $feedback = $this->feedback->compute($docScope, $range);
            $return = $this->patientReturn->compute($docScope, $range);
            $returnAny = $this->patientReturn->computeAnyDoctor($docScope, $range);
            $appts = $this->appointments->compute($docScope, $range);

            $avgValue = $conversion['total_converted'] > 0
                ? round($revenue['total_revenue'] / $conversion['total_converted'], 2)
                : 0.0;

            $trendSeries = $this->trendSeries($docScope, $monthRanges);
            $trendSlopePct = $this->trendSlopePct($trendSeries);

            $rows[] = [
                'resource_id' => $doctorId,
                'resource_type' => ResourceType::Doctor->value,
                'resource_name' => $names[$doctorId] ?? "User #{$doctorId}",
                'revenue' => $revenue['total_revenue'],
                'conversion_rate' => $conversion['conversion_rate'],
                'avg_client_value' => $avgValue,
                'upsell_revenue' => $upsell['upsell_revenue'],
                'feedback_score' => $feedback['avg_rating'],
                'return_rate' => $return['return_rate'],
                'return_rate_any_doctor' => $returnAny['return_rate'],
                'patients_seen' => $appts['total'],
                'trend_series' => $trendSeries,
                'trend_slope_pct' => $trendSlopePct,
            ];
        }

        usort($rows, static fn (array $a, array $b): int => $b['revenue'] <=> $a['revenue']);

        return [
            'rows' => $rows,
            'company_avg' => $this->companyAverages($rows),
        ];
    }

    /**
     * Build N trailing monthly anchor ranges, oldest→newest, ending in the
     * calendar month containing the given range's end date. Each range
     * spans the full calendar month so every series point sits on the
     * same monthly grid regardless of the user's selected window.
     *
     * @return list<DateRange>
     */
    private function trailingMonthRanges(DateRange $range, int $months): array
    {
        $anchor = Carbon::parse($range->endString())->endOfMonth();
        $out = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $monthEnd = $anchor->copy()->subMonthsNoOverflow($i)->endOfMonth();
            $monthStart = $monthEnd->copy()->startOfMonth();
            $out[] = DateRange::fromStrings(
                $monthStart->format('Y-m-d'),
                $monthEnd->format('Y-m-d'),
            );
        }

        return $out;
    }

    /**
     * Per-doctor monthly revenue series across the supplied anchor ranges.
     *
     * @param  list<DateRange>  $monthRanges
     * @return list<float>
     */
    private function trendSeries(MetricScope $docScope, array $monthRanges): array
    {
        $series = [];
        foreach ($monthRanges as $r) {
            $rev = $this->revenue->compute($docScope, $r);
            $series[] = (float) $rev['total_revenue'];
        }

        return $series;
    }

    /**
     * Linear-regression slope of monthly revenue, expressed as %/month
     * relative to the series mean. Null when fewer than two non-zero
     * months — slope on a single value is meaningless.
     *
     * @param  list<float>  $series
     */
    private function trendSlopePct(array $series): ?float
    {
        $n = count($series);
        if ($n < 2) {
            return null;
        }

        $nonZero = array_filter($series, static fn (float $v): bool => $v > 0);
        if (count($nonZero) < 2) {
            return null;
        }

        $xMean = ($n - 1) / 2.0;
        $yMean = array_sum($series) / $n;
        if ($yMean <= 0) {
            return null;
        }

        $num = 0.0;
        $den = 0.0;
        foreach ($series as $i => $y) {
            $dx = $i - $xMean;
            $num += $dx * ($y - $yMean);
            $den += $dx * $dx;
        }
        if ($den === 0.0) {
            return null;
        }

        $slope = $num / $den;

        // Express as a percentage of the series mean — a per-month % change.
        return round(($slope / $yMean) * 100, 1);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, float>
     */
    private function companyAverages(array $rows): array
    {
        if ($rows === []) {
            return $this->emptyAverages();
        }

        $n = count($rows);
        $keys = [
            'revenue', 'conversion_rate', 'avg_client_value', 'upsell_revenue',
            'feedback_score', 'return_rate', 'return_rate_any_doctor', 'patients_seen',
        ];

        $avg = [];
        foreach ($keys as $key) {
            $sum = array_sum(array_column($rows, $key));
            $avg[$key] = round($sum / $n, 2);
        }

        return $avg;
    }

    /**
     * @return array<string, float>
     */
    private function emptyAverages(): array
    {
        return [
            'revenue' => 0.0, 'conversion_rate' => 0.0, 'avg_client_value' => 0.0,
            'upsell_revenue' => 0.0, 'feedback_score' => 0.0, 'return_rate' => 0.0,
            'return_rate_any_doctor' => 0.0, 'patients_seen' => 0.0,
        ];
    }
}
