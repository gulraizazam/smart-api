<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Metrics;

use App\Services\Dashboard\Contracts\Metric;
use App\Services\Dashboard\ValueObjects\DateRange;
use App\Services\Dashboard\ValueObjects\MetricScope;
use Illuminate\Support\Facades\DB;

/**
 * Lead → Patient funnel split by gender.
 *
 * Four cumulative stages, each "reached X or beyond":
 *   1. Generated  — all leads created in range
 *   2. Booked     — lead_status_id ∈ {Booked(4), Arrived(6,10), Converted(9,11)}
 *   3. Arrived    — lead_status_id ∈ {Arrived(6,10), Converted(9,11)}
 *   4. Converted  — lead_status_id ∈ {Converted(9,11)}
 *
 * Funnel semantics (cumulative, not current-status) — a lead that reached
 * Booked and later Converted counts in ALL of Booked, Arrived, Converted.
 * This is how a funnel must read: downstream stages are subsets of upstream.
 *
 * Gender rule (per product decision):
 *   - If lead.patient_id is set → always use patients.gender (current value,
 *     so gender corrections on the patient record propagate to historical
 *     leads automatically — no backfill needed).
 *   - Else → fall back to leads.gender (lead-form captured value).
 *   - Zero / unrecognised → "unknown".
 *
 * Respects MetricScope (account + optional branch subset via leads.location_id).
 *
 * Return shape: each stage is a keyed block with M / F / U counts + total.
 *   [
 *     'generated' => ['male' => int, 'female' => int, 'unknown' => int, 'total' => int],
 *     'booked'    => ...,
 *     'arrived'   => ...,
 *     'converted' => ...,
 *   ]
 */
final class LeadGenderFunnelMetric implements Metric
{
    /** lead_statuses ids — comments are the `name` column values. */
    private const STATUS_BOOKED_OR_BEYOND = [4, 6, 10, 9, 11]; // Booked, Arrived, Arrived, Converted, Converted

    private const STATUS_ARRIVED_OR_BEYOND = [6, 10, 9, 11];   // Arrived, Arrived, Converted, Converted

    private const STATUS_CONVERTED = [9, 11];                  // Converted, Converted

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

        $query = DB::table('leads as l')
            ->leftJoin('users as p', function ($join) {
                $join->on('p.id', '=', 'l.patient_id')
                    ->where('p.user_type_id', 3);
            })
            ->where('l.account_id', $scope->accountId)
            ->whereNull('l.deleted_at')
            ->whereBetween('l.created_at', [$start, $end]);

        if ($scope->isBranchScoped() && $scope->branchIds !== null) {
            if ($scope->branchIds === []) {
                return $this->empty();
            }
            $query->whereIn('l.location_id', $scope->branchIds);
        }

        $bookedList = implode(',', self::STATUS_BOOKED_OR_BEYOND);
        $arrivedList = implode(',', self::STATUS_ARRIVED_OR_BEYOND);
        $convertedList = implode(',', self::STATUS_CONVERTED);

        // Aliases prefixed with `cnt_` to sidestep MariaDB reserved words —
        // `generated` and `converted` are both keywords in MariaDB 11.
        $rows = $query
            ->selectRaw(
                'CASE COALESCE(NULLIF(p.gender, 0), NULLIF(l.gender, 0)) '.
                "WHEN 1 THEN 'male' WHEN 2 THEN 'female' ELSE 'unknown' END AS effective_gender, ".
                'COUNT(*) AS cnt_generated, '.
                "SUM(CASE WHEN l.lead_status_id IN ({$bookedList}) THEN 1 ELSE 0 END) AS cnt_booked, ".
                "SUM(CASE WHEN l.lead_status_id IN ({$arrivedList}) THEN 1 ELSE 0 END) AS cnt_arrived, ".
                "SUM(CASE WHEN l.lead_status_id IN ({$convertedList}) THEN 1 ELSE 0 END) AS cnt_converted"
            )
            ->groupBy('effective_gender')
            ->get();

        $stageCols = [
            'generated' => 'cnt_generated',
            'booked' => 'cnt_booked',
            'arrived' => 'cnt_arrived',
            'converted' => 'cnt_converted',
        ];
        $out = [];
        foreach ($stageCols as $key => $_) {
            $out[$key] = ['male' => 0, 'female' => 0, 'unknown' => 0, 'total' => 0];
        }

        foreach ($rows as $row) {
            $g = (string) $row->effective_gender;
            if (! in_array($g, ['male', 'female', 'unknown'], true)) {
                continue;
            }
            foreach ($stageCols as $key => $col) {
                $out[$key][$g] += (int) $row->{$col};
                $out[$key]['total'] += (int) $row->{$col};
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function empty(): array
    {
        $zero = ['male' => 0, 'female' => 0, 'unknown' => 0, 'total' => 0];

        return [
            'generated' => $zero,
            'booked' => $zero,
            'arrived' => $zero,
            'converted' => $zero,
        ];
    }
}
