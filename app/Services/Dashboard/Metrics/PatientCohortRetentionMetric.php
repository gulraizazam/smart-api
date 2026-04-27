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

        // Bulk approach — replaces a per-month, per-window fan-out
        // (12 cohort queries × 4 window queries = 60 trips) with three
        // queries total:
        //   1. Patient IDs with any qualifying appointment in the window
        //      that includes all cohort months PLUS the longest retention
        //      window after the latest month (so returns count correctly).
        //   2. Lifetime first-visit per those patients, filtered to those
        //      whose first-visit lands in the cohort range.
        //   3. All qualifying return appointments for cohort patients.
        // Then we bucket month + window in PHP.
        $earliestMonthStart = end($monthBounds)['start'];
        $latestMonthEnd = $monthBounds[$months[0]]['end'];
        $maxWindow = max($windows);
        $returnsBoundEnd = CarbonImmutable::parse($latestMonthEnd)
            ->addDays($maxWindow)
            ->format('Y-m-d');

        $cohortFirstVisits = $this->cohortFirstVisitsBulk(
            $scope,
            $earliestMonthStart,
            $latestMonthEnd,
            $returnsBoundEnd,
        );

        // Group cohort patients by their first-visit month label.
        $byMonth = [];
        foreach ($cohortFirstVisits as $patientId => $firstDate) {
            $monthLabel = substr($firstDate, 0, 7); // YYYY-MM
            $byMonth[$monthLabel][$patientId] = $firstDate;
        }

        // One pass to fetch all return appointments for cohort patients.
        $cohortPatientIds = array_keys($cohortFirstVisits);
        $returnsByPatient = $this->returnsByPatientBulk($cohortPatientIds, $earliestMonthStart, $returnsBoundEnd);

        $cohorts = [];
        foreach ($months as $label) {
            $monthCohort = $byMonth[$label] ?? [];
            $cohortSize = count($monthCohort);
            if ($cohortSize === 0) {
                $cohorts[$label] = ['size' => 0, 'retention' => array_fill_keys($windows, 0.0)];

                continue;
            }

            $retention = [];
            foreach ($windows as $window) {
                $returned = 0;
                foreach ($monthCohort as $patientId => $firstDate) {
                    $windowEnd = date('Y-m-d', strtotime($firstDate.' +'.$window.' days'));
                    foreach ($returnsByPatient[$patientId] ?? [] as $d) {
                        if ($d > $firstDate && $d <= $windowEnd) {
                            $returned++;
                            break;
                        }
                    }
                }
                $retention[$window] = round(($returned / $cohortSize) * 100, 1);
            }

            $cohorts[$label] = [
                'size' => $cohortSize,
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
     * Lifetime first-visit per patient, filtered to patients whose first
     * visit falls inside the cohort range. Scopes the GROUP BY to
     * patients active in the relevant window so the planner doesn't
     * aggregate across every patient ever.
     *
     * @return array<int, string> patient_id => YYYY-MM-DD
     */
    private function cohortFirstVisitsBulk(
        MetricScope $scope,
        string $earliestMonthStart,
        string $latestMonthEnd,
        string $returnsBoundEnd,
    ): array {
        $statusIds = array_unique(array_merge(
            DoctorDashboardHelper::getConsultationStatusIds(),
            DoctorDashboardHelper::getTreatmentStatusIds(),
        ));

        // Step 1 — patient IDs with any qualifying appointment in the
        // window of interest. Cheap indexed scan.
        $pidQ = DB::table('appointments')
            ->where('account_id', $scope->accountId)
            ->whereIn('appointment_status_id', $statusIds)
            ->whereBetween('scheduled_date', [$earliestMonthStart, $returnsBoundEnd])
            ->whereNull('deleted_at');
        if ($scope->isBranchScoped() && $scope->branchIds !== null) {
            $pidQ->whereIn('location_id', $scope->branchIds);
        }
        $patientIds = $pidQ->distinct()->pluck('patient_id')->map(static fn ($v): int => (int) $v)->all();

        if ($patientIds === []) {
            return [];
        }

        // Step 2 — for those patients, lifetime MIN(scheduled_date),
        // filtered (HAVING) to those whose first visit lands in the
        // cohort range.
        $out = [];
        foreach (array_chunk($patientIds, 5000) as $chunk) {
            $rows = DB::table('appointments')
                ->where('account_id', $scope->accountId)
                ->whereIn('appointment_status_id', $statusIds)
                ->whereIn('patient_id', $chunk)
                ->whereNull('deleted_at')
                ->select('patient_id')
                ->selectRaw('MIN(scheduled_date) AS first_date')
                ->groupBy('patient_id')
                ->having('first_date', '>=', $earliestMonthStart)
                ->having('first_date', '<=', $latestMonthEnd)
                ->get();
            foreach ($rows as $r) {
                $out[(int) $r->patient_id] = (string) $r->first_date;
            }
        }

        return $out;
    }

    /**
     * Return appointments for the cohort patients within the relevant
     * outer window. One query covers every (patient, window) check we
     * need — bucketing happens in PHP.
     *
     * @param  list<int>  $patientIds
     * @return array<int, list<string>>
     */
    private function returnsByPatientBulk(array $patientIds, string $rangeStart, string $rangeEnd): array
    {
        if ($patientIds === []) {
            return [];
        }

        $statusIds = array_unique(array_merge(
            DoctorDashboardHelper::getConsultationStatusIds(),
            DoctorDashboardHelper::getTreatmentStatusIds(),
        ));

        $out = [];
        foreach (array_chunk($patientIds, 5000) as $chunk) {
            $rows = DB::table('appointments')
                ->whereIn('patient_id', $chunk)
                ->whereIn('appointment_status_id', $statusIds)
                ->whereBetween('scheduled_date', [$rangeStart, $rangeEnd])
                ->whereNull('deleted_at')
                ->select('patient_id', 'scheduled_date')
                ->get();
            foreach ($rows as $r) {
                $out[(int) $r->patient_id][] = (string) $r->scheduled_date;
            }
        }

        return $out;
    }

}
