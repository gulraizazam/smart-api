<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Rollup;

use App\Models\CentertargetMeta;
use App\Models\DashboardDailyMetric;
use App\Services\Dashboard\Metrics\AppointmentsMetric;
use App\Services\Dashboard\Metrics\ConversionMetric;
use App\Services\Dashboard\Metrics\RefundOffsetMetric;
use App\Services\Dashboard\Metrics\RevenueMetric;
use App\Services\Dashboard\ValueObjects\DateRange;
use App\Services\Dashboard\ValueObjects\MetricScope;
use Illuminate\Support\Facades\DB;

/**
 * Writes one day's pre-aggregated metric rows to `dashboard_daily_metrics`.
 *
 * Idempotent: uses updateOrCreate so reruns overwrite safely.
 * target_value is pinned at write time so that later target edits don't
 * silently rewrite historical "% to target" figures.
 *
 * Scope coverage per day: company-wide + one row per branch. Per-resource
 * (doctor/therapist/consultant) rollups are intentionally excluded from the
 * v1 builder — those are computed live through ResourceLeaderboardMetric for
 * now. Adding them is a row-count × scope expansion, not a logic change.
 */
final class DailyMetricsBuilder
{
    public function __construct(
        private readonly RevenueMetric $revenue,
        private readonly ConversionMetric $conversion,
        private readonly AppointmentsMetric $appointments,
        private readonly RefundOffsetMetric $refunds,
    ) {}

    /**
     * Rebuild all rows for (account_id, date). Runs in a single DB transaction
     * so partial failures don't leave a half-built day.
     */
    public function rebuild(int $accountId, string $date): int
    {
        $range = DateRange::fromStrings($date, $date);
        $written = 0;

        DB::transaction(function () use ($accountId, $range, &$written): void {
            // Company-wide scope (branch_id = null).
            $companyScope = MetricScope::company($accountId);
            $written += $this->writeScope($accountId, null, $companyScope, $range);

            // Per-branch scopes.
            foreach ($this->branchIds($accountId) as $branchId) {
                $branchScope = MetricScope::branches($accountId, [$branchId]);
                $written += $this->writeScope($accountId, $branchId, $branchScope, $range);
            }
        });

        return $written;
    }

    private function writeScope(int $accountId, ?int $branchId, MetricScope $scope, DateRange $range): int
    {
        $date = $range->startString();
        $target = $branchId !== null ? $this->branchDailyTarget($accountId, $branchId, $date) : null;

        // Compute each metric once and reuse fields. Previously `conversion`
        // and `appointments` were computed twice per scope — halves the query
        // count of the nightly rebuild.
        $conversion = $this->conversion->compute($scope, $range);
        $appointments = $this->appointments->compute($scope, $range);

        $metrics = [
            ['net_revenue', (float) $this->revenue->compute($scope, $range)['total_revenue'], $target],
            ['conversion_rate', (float) $conversion['conversion_rate'], null],
            ['conversions', (float) $conversion['total_converted'], null],
            ['appointments_completed', (float) $appointments['total'], null],
            ['no_show_rate', (float) $this->appointments->noShowRate($scope, $range)['rate'], null],
            ['refund_total', (float) $this->refunds->compute($scope, $range)['refund_total'], null],
        ];

        $count = 0;
        foreach ($metrics as [$key, $value, $targetValue]) {
            DashboardDailyMetric::updateOrCreate(
                [
                    'account_id' => $accountId,
                    'date' => $date,
                    'branch_id' => $branchId,
                    'resource_id' => null,
                    'resource_type' => null,
                    'metric_key' => $key,
                ],
                [
                    'value' => $value,
                    'target_value' => $key === 'net_revenue' ? $targetValue : null,
                    'dimensions' => null,
                ],
            );
            $count++;
        }

        return $count;
    }

    private function branchDailyTarget(int $accountId, int $branchId, string $date): ?float
    {
        // Pin to UTC and strict-parse the date. Lenient Carbon::parse() can
        // shift by ±1 day if the system or app timezone differs from UTC,
        // which would mis-bucket the nightly rollup at month boundaries.
        $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $date, 'UTC');

        if ($parsed === false) {
            return null;
        }

        $row = CentertargetMeta::query()
            ->join('centertarget as ct', 'centretargetmeta.centertarget_id', '=', 'ct.id')
            ->where('centretargetmeta.location_id', $branchId)
            ->where('ct.account_id', $accountId)
            ->where('ct.month', (int) $parsed->format('m'))
            ->where('ct.year', (int) $parsed->format('Y'))
            ->value('centretargetmeta.target_amount');

        return $row !== null ? (float) $row : null;
    }

    /**
     * @return list<int>
     */
    private function branchIds(int $accountId): array
    {
        return array_map(
            'intval',
            DB::table('locations')
                ->where('account_id', $accountId)
                ->whereNull('deleted_at')
                ->where('name', 'not like', 'All %')
                ->pluck('id')
                ->toArray(),
        );
    }
}
