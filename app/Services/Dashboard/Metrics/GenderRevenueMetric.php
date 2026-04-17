<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Metrics;

use App\Services\Dashboard\Contracts\Metric;
use App\Services\Dashboard\ValueObjects\DateRange;
use App\Services\Dashboard\ValueObjects\MetricScope;
use Illuminate\Support\Facades\DB;

/**
 * Revenue split by patient gender (male / female / unknown).
 *
 * Filters mirror the legacy GenderWiseRevenueReport exactly:
 *  - package_advances.cash_flow = 'in'
 *  - is_adjustment = '0', is_tax = '0', is_cancel = '0'
 *  - cash_amount > 0
 *  - appointment_type_id = 1 (consultation-linked cash only — matches the
 *    existing Gender-Wise Revenue report in the Reports module)
 *
 * // CUTERA-REVIEW: appointment_type_id=1 filter comes from the legacy report.
 * // If management wants total revenue (consultations + treatments) split by
 * // gender, drop that clause. Flagged for product decision.
 *
 * Respects MetricScope (account + optional branch subset via packages.location_id).
 *
 * Return shape:
 *   [
 *     'male'    => ['revenue' => float, 'count' => int, 'patients' => int, 'share' => float, 'avg_per_patient' => float],
 *     'female'  => ['revenue' => float, 'count' => int, 'patients' => int, 'share' => float, 'avg_per_patient' => float],
 *     'unknown' => ['revenue' => float, 'count' => int, 'patients' => int, 'share' => float, 'avg_per_patient' => float],
 *     'total'   => ['revenue' => float, 'count' => int, 'patients' => int],
 *   ]
 *
 * `count` = txn count (package_advances rows). `patients` = distinct
 * patient_id across the matched rows — better denominator for avg-revenue-
 * per-customer than per-txn (one patient paying twice shouldn't double-count).
 */
final class GenderRevenueMetric implements Metric
{
    /**
     * @return array<string, mixed>
     */
    public function compute(MetricScope $scope, DateRange $range): array
    {
        if ($scope->isDenyAll()) {
            return $this->empty();
        }

        $start = $range->startString().' 00:00:00';
        $end = $range->endString().' 23:59:59';

        // Patients live in the `users` table with user_type_id = 3. The Patients
        // Eloquent model maps there and adds a global scope for that filter; we
        // join raw here, so the type filter is explicit.
        $query = DB::table('package_advances as pa')
            ->join('packages as pk', 'pk.id', '=', 'pa.package_id')
            ->join('appointments as a', 'a.id', '=', 'pk.appointment_id')
            ->leftJoin('users as p', function ($join) {
                $join->on('p.id', '=', 'a.patient_id')
                    ->where('p.user_type_id', 3);
            })
            ->where('pa.account_id', $scope->accountId)
            ->where('pa.cash_flow', 'in')
            ->where('pa.is_adjustment', '0')
            ->where('pa.is_tax', '0')
            ->where('pa.is_cancel', '0')
            ->where('pa.cash_amount', '>', 0)
            ->whereBetween('pa.created_at', [$start, $end])
            ->where('a.appointment_type_id', 1);

        if ($scope->isBranchScoped() && $scope->branchIds !== null) {
            if ($scope->branchIds === []) {
                return $this->empty();
            }
            $query->whereIn('pk.location_id', $scope->branchIds);
        }

        $rows = $query
            ->selectRaw(
                "CASE WHEN p.gender = 1 THEN 'male' ".
                "WHEN p.gender = 2 THEN 'female' ".
                "ELSE 'unknown' END as gender_label, ".
                'SUM(pa.cash_amount) as revenue, '.
                'COUNT(*) as count, '.
                'COUNT(DISTINCT a.patient_id) as patients'
            )
            ->groupBy('gender_label')
            ->get();

        $buckets = [
            'male' => ['revenue' => 0.0, 'count' => 0, 'patients' => 0],
            'female' => ['revenue' => 0.0, 'count' => 0, 'patients' => 0],
            'unknown' => ['revenue' => 0.0, 'count' => 0, 'patients' => 0],
        ];

        foreach ($rows as $row) {
            $key = (string) $row->gender_label;
            if (! isset($buckets[$key])) {
                continue;
            }
            $buckets[$key] = [
                'revenue' => round((float) $row->revenue, 2),
                'count' => (int) $row->count,
                'patients' => (int) $row->patients,
            ];
        }

        $totalRevenue = array_sum(array_column($buckets, 'revenue'));
        $totalCount = array_sum(array_column($buckets, 'count'));
        $totalPatients = array_sum(array_column($buckets, 'patients'));

        foreach ($buckets as $key => $bucket) {
            $buckets[$key]['share'] = $totalRevenue > 0
                ? round(($bucket['revenue'] / $totalRevenue) * 100, 1)
                : 0.0;
            $buckets[$key]['avg_per_patient'] = $bucket['patients'] > 0
                ? round($bucket['revenue'] / $bucket['patients'], 2)
                : 0.0;
        }

        return [
            'male' => $buckets['male'],
            'female' => $buckets['female'],
            'unknown' => $buckets['unknown'],
            'total' => [
                'revenue' => round((float) $totalRevenue, 2),
                'count' => (int) $totalCount,
                'patients' => (int) $totalPatients,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function empty(): array
    {
        $zero = ['revenue' => 0.0, 'count' => 0, 'patients' => 0, 'share' => 0.0, 'avg_per_patient' => 0.0];

        return [
            'male' => $zero,
            'female' => $zero,
            'unknown' => $zero,
            'total' => ['revenue' => 0.0, 'count' => 0, 'patients' => 0],
        ];
    }
}
