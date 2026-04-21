<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Support;

use Illuminate\Support\Facades\DB;

/**
 * Canonical "appointments in period" counter. Returns the total count for
 * a given appointment type plus the "done" subset (appointments whose
 * status is in a caller-supplied qualifying set — typically "arrived" for
 * treatments, "arrived or converted" for consultancies).
 *
 * Single source of truth for the home page ("Consultations X/Y",
 * "Treatments X/Y"), the Management Dashboard Stats card, and any future
 * place that needs the same semantics. Before this helper existed, the
 * rule was re-implemented in three places — DashboardStatsService
 * (Eloquent + legacy helpers), AppointmentsMetric (DB facade +
 * hard-coded status IDs), and dashboardreport. Drift risk was high:
 * one caller used dynamic DB-resolved status IDs, another used
 * `[2, 16]` as constants.
 */
final class AppointmentCountsQuery
{
    /**
     * Count appointments for one type in one scope + period. `done_count`
     * is the subset whose status matches $qualifyingStatusIds; pass an
     * empty list to disable the "done" count.
     *
     * @param  list<int>  $locationIds  (empty = no branch filter)
     * @param  list<int>  $qualifyingStatusIds
     * @return array{all_count: int, done_count: int}
     */
    public static function forType(
        int $accountId,
        array $locationIds,
        int $appointmentTypeId,
        array $qualifyingStatusIds,
        string $startDate,
        string $endDate,
        ?int $doctorId = null,
    ): array {
        $query = DB::table('appointments')
            ->where('account_id', $accountId)
            ->where('appointment_type_id', $appointmentTypeId)
            ->whereBetween('scheduled_date', [$startDate, $endDate])
            ->whereNull('deleted_at');

        if ($locationIds !== []) {
            $query->whereIn('location_id', $locationIds);
        }
        if ($doctorId !== null) {
            $query->where('doctor_id', $doctorId);
        }

        if ($qualifyingStatusIds === []) {
            $row = $query->selectRaw('COUNT(*) AS all_count, 0 AS done_count')->first();
        } else {
            $placeholders = implode(',', array_fill(0, count($qualifyingStatusIds), '?'));
            $row = $query->selectRaw(
                "COUNT(*) AS all_count, SUM(CASE WHEN appointment_status_id IN ({$placeholders}) THEN 1 ELSE 0 END) AS done_count",
                $qualifyingStatusIds,
            )->first();
        }

        return [
            'all_count' => (int) ($row->all_count ?? 0),
            'done_count' => (int) ($row->done_count ?? 0),
        ];
    }
}
