<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Metrics;

use App\Helpers\DoctorDashboardHelper;
use App\Services\Dashboard\Contracts\Metric;
use App\Services\Dashboard\ValueObjects\DateRange;
use App\Services\Dashboard\ValueObjects\MetricScope;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Monthly cohort retention grid for the Patients section.
 *
 * Cohort = patients whose first visit (arrived consultation or treatment) fell
 * within that calendar month.
 * Retention at window W = % of cohort who had another arrived appointment
 * within W days of their first visit.
 *
 * Default: last 12 months × 30/60/90/180 day windows. Toggle expands to 24
 * months and adds 365d window.
 *
 * The $range parameter is used only to anchor the "latest" cohort month.
 *
 * Return shape:
 *   months:   list of "YYYY-MM" labels, newest first
 *   windows:  list<int>  (day counts)
 *   cohorts:  { [monthLabel]: { size: int, retention: { [window]: float } } }
 */
final class PatientCohortRetentionMetric implements Metric
{
    private const CACHE_TTL = 300;

    /**
     * @return array{
     *   months: list<string>,
     *   windows: list<int>,
     *   cohorts: array<string, array{size: int, retention: array<int, float>}>
     * }
     */
    public function compute(
        MetricScope $scope,
        DateRange $range,
        int $monthsBack = 12,
        bool $extendedWindows = false,
    ): array {
        if ($scope->isDenyAll()) {
            return ['months' => [], 'windows' => [], 'cohorts' => []];
        }

        $cacheKey = 'mgmt_dash:patient_cohort_retention:'
            .$scope->cacheKey()
            .'|'.$range->startString().'..'.$range->endString()
            .'|mb='.$monthsBack.'|ext='.($extendedWindows ? 1 : 0);

        return Cache::remember(
            $cacheKey,
            self::CACHE_TTL,
            fn () => $this->build($scope, $range, $monthsBack, $extendedWindows),
        );
    }

    /**
     * @return array{
     *   months: list<string>,
     *   windows: list<int>,
     *   cohorts: array<string, array{size: int, retention: array<int, float>}>
     * }
     */
    private function build(
        MetricScope $scope,
        DateRange $range,
        int $monthsBack,
        bool $extendedWindows,
    ): array {
        $windows = $extendedWindows ? [30, 60, 90, 180, 365] : [30, 60, 90, 180];
        $end = CarbonImmutable::parse($range->endString())->endOfMonth();

        $months = [];
        $monthBounds = [];
        for ($i = 0; $i < $monthsBack; $i++) {
            $m = $end->subMonthsNoOverflow($i);
            $label = $m->format('Y-m');
            $months[] = $label;
            $monthBounds[$label] = [
                'start' => $m->startOfMonth()->format('Y-m-d'),
                'end' => $m->endOfMonth()->format('Y-m-d'),
            ];
        }

        $cohorts = [];
        foreach ($months as $label) {
            $bounds = $monthBounds[$label];
            $firstVisits = $this->firstVisitsInMonth($scope, $bounds['start'], $bounds['end']);

            if ($firstVisits === []) {
                $cohorts[$label] = ['size' => 0, 'retention' => array_fill_keys($windows, 0.0)];

                continue;
            }

            $retention = [];
            $cohortSize = count($firstVisits);
            foreach ($windows as $window) {
                $returned = $this->countReturnedWithin($firstVisits, $window);
                $retention[$window] = round(($returned / $cohortSize) * 100, 1);
            }

            $cohorts[$label] = [
                'size' => count($firstVisits),
                'retention' => $retention,
            ];
        }

        return [
            'months' => $months,
            'windows' => $windows,
            'cohorts' => $cohorts,
        ];
    }

    /**
     * Patients whose very first arrived appointment fell in this month.
     *
     * @return array<int, string> [patient_id => first_visit_date]
     */
    private function firstVisitsInMonth(MetricScope $scope, string $monthStart, string $monthEnd): array
    {
        $qualifyingStatusIds = array_unique(array_merge(
            DoctorDashboardHelper::getConsultationStatusIds(),
            DoctorDashboardHelper::getTreatmentStatusIds(),
        ));

        $query = DB::table('appointments')
            ->where('account_id', $scope->accountId)
            ->whereIn('appointment_status_id', $qualifyingStatusIds)
            ->whereNull('deleted_at');

        if ($scope->isBranchScoped() && $scope->branchIds !== null) {
            $query->whereIn('location_id', $scope->branchIds);
        }

        $firstPerPatient = $query
            ->select('patient_id', DB::raw('MIN(scheduled_date) as first_date'))
            ->groupBy('patient_id');

        $sql = DB::query()
            ->fromSub($firstPerPatient, 'fp')
            ->whereBetween('first_date', [$monthStart, $monthEnd])
            ->pluck('first_date', 'patient_id');

        $out = [];
        foreach ($sql as $patientId => $date) {
            $out[(int) $patientId] = (string) $date;
        }

        return $out;
    }

    /**
     * Of the given {patient_id => first_visit_date} pairs, count how many had
     * a return visit within $windowDays.
     *
     * @param  array<int, string>  $firstVisits
     */
    private function countReturnedWithin(array $firstVisits, int $windowDays): int
    {
        if ($firstVisits === []) {
            return 0;
        }

        $patientIds = array_keys($firstVisits);
        $qualifyingStatusIds = array_unique(array_merge(
            DoctorDashboardHelper::getConsultationStatusIds(),
            DoctorDashboardHelper::getTreatmentStatusIds(),
        ));

        $rows = DB::table('appointments')
            ->whereIn('patient_id', $patientIds)
            ->whereIn('appointment_status_id', $qualifyingStatusIds)
            ->whereNull('deleted_at')
            ->select('patient_id', 'scheduled_date')
            ->get()
            ->groupBy('patient_id');

        $returned = 0;
        foreach ($firstVisits as $patientId => $firstDate) {
            $windowEnd = date('Y-m-d', strtotime($firstDate.' +'.$windowDays.' days'));
            $patientRows = $rows->get($patientId, collect());
            $hasReturn = $patientRows->contains(static fn (object $r): bool => $r->scheduled_date > $firstDate
                && $r->scheduled_date <= $windowEnd);

            if ($hasReturn) {
                $returned++;
            }
        }

        return $returned;
    }
}
