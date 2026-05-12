<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Metrics;

use App\Services\Dashboard\Support\BranchResolver;
use App\Services\Dashboard\ValueObjects\MetricScope;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Monthly feedback-rating trend — powers the Trend tab on the Branch
 * Feedback Score panel. Two flavours:
 *
 *   - branchTrend()       : one row per branch in scope + a pooled row
 *   - branchDoctorTrend() : one row per active doctor in a single branch
 *                            + a branch-level pool row
 *
 * Each "point" is the simple average rating across feedback rows whose
 * underlying treatment appointment was scheduled in that calendar month.
 * The set of feedbacks is identical to BranchFeedbackMetric (treatment
 * type=2, status=2, today excluded) so the snapshot/trend numbers reconcile.
 *
 * Aggregation is one grouped query per flavour over the whole window —
 * cheaper than N anchor-by-anchor queries and gives MariaDB a chance to
 * use the same index for every month.
 */
final class BranchFeedbackTrendMetric
{
    private const CACHE_TTL = 300;

    private const TREATMENT_TYPE_ID = 2;

    private const TREATMENT_STATUS_ID = 2;

    public function __construct(
        private readonly BranchResolver $branches,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function branchTrend(MetricScope $scope, int $monthsBack = 6): array
    {
        $branchIds = $this->branches->idsInScope($scope);
        if ($branchIds === []) {
            return ['months' => [], 'rows' => [], 'pool' => []];
        }

        $cacheKey = 'mgmt_dash:branch_feedback_trend:'
            .$scope->cacheKey()
            .'|m='.$monthsBack;

        return Cache::remember(
            $cacheKey,
            self::CACHE_TTL,
            fn () => $this->buildBranchTrend($branchIds, $monthsBack),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function branchDoctorTrend(MetricScope $scope, int $branchId, int $monthsBack = 6): array
    {
        if ($scope->isDenyAll()) {
            return $this->emptyDoctorResponse($branchId, null);
        }
        if ($scope->isBranchScoped() && $scope->branchIds !== null && ! in_array($branchId, $scope->branchIds, true)) {
            return $this->emptyDoctorResponse($branchId, null);
        }

        $cacheKey = 'mgmt_dash:branch_doctor_feedback_trend:'
            .$scope->cacheKey()
            .'|b='.$branchId
            .'|m='.$monthsBack;

        return Cache::remember(
            $cacheKey,
            self::CACHE_TTL,
            fn () => $this->buildDoctorTrend($branchId, $monthsBack),
        );
    }

    /**
     * @param  list<int>  $branchIds
     * @return array<string, mixed>
     */
    private function buildBranchTrend(array $branchIds, int $monthsBack): array
    {
        [$anchors, $monthKeys, $windowStart, $windowEnd] = $this->buildWindow($monthsBack);

        // Single grouped query over the whole window, keyed (branch, month).
        $rows = DB::table('feedback')
            ->join('appointments', 'feedback.appointment_id', '=', 'appointments.id')
            ->whereIn('appointments.location_id', $branchIds)
            ->where('appointments.appointment_type_id', self::TREATMENT_TYPE_ID)
            ->where('appointments.appointment_status_id', self::TREATMENT_STATUS_ID)
            ->whereBetween('appointments.scheduled_date', [$windowStart, $windowEnd])
            ->whereNull('appointments.deleted_at')
            ->select('appointments.location_id as location_id')
            ->selectRaw("DATE_FORMAT(appointments.scheduled_date, '%Y-%m') AS ym")
            ->selectRaw('AVG(CAST(feedback.rating AS DECIMAL(4,2))) AS avg_rating')
            ->selectRaw('COUNT(DISTINCT feedback.appointment_id) AS total_feedback')
            ->groupBy('appointments.location_id')
            ->groupByRaw("DATE_FORMAT(appointments.scheduled_date, '%Y-%m')")
            ->get();

        $perBranch = [];
        foreach ($branchIds as $bid) {
            $perBranch[$bid] = [];
            foreach ($monthKeys as $key) {
                $perBranch[$bid][$key] = ['avg' => null, 'total' => 0];
            }
        }
        foreach ($rows as $row) {
            $bid = (int) $row->location_id;
            $key = (string) $row->ym;
            if (! isset($perBranch[$bid][$key])) {
                continue;
            }
            $perBranch[$bid][$key] = [
                'avg' => $row->avg_rating !== null ? round((float) $row->avg_rating, 2) : null,
                'total' => (int) $row->total_feedback,
            ];
        }

        // Pool = avg across all branches per month, weighted by feedback
        // count so a high-volume branch isn't drowned by an outlier branch
        // with only a handful of low-score feedbacks.
        $poolPerMonth = [];
        foreach ($monthKeys as $key) {
            $sumWeight = 0;
            $sumCount = 0;
            foreach ($branchIds as $bid) {
                $bucket = $perBranch[$bid][$key];
                if ($bucket['avg'] === null || $bucket['total'] <= 0) {
                    continue;
                }
                $sumWeight += $bucket['avg'] * $bucket['total'];
                $sumCount += $bucket['total'];
            }
            $poolPerMonth[] = [
                'month' => $key,
                'avg_rating' => $sumCount > 0 ? round($sumWeight / $sumCount, 2) : null,
                'total_feedback' => $sumCount,
            ];
        }

        $branchNames = $this->branches->names($branchIds);
        $resultRows = [];
        foreach ($branchIds as $bid) {
            $points = [];
            foreach ($monthKeys as $key) {
                $points[] = [
                    'month' => $key,
                    'avg_rating' => $perBranch[$bid][$key]['avg'],
                    'total_feedback' => $perBranch[$bid][$key]['total'],
                ];
            }
            $resultRows[] = [
                'branch_id' => $bid,
                'branch_name' => $branchNames[$bid] ?? "Branch #{$bid}",
                'points' => $points,
            ];
        }

        usort($resultRows, function (array $a, array $b): int {
            $lastA = $a['points'][count($a['points']) - 1]['avg_rating'] ?? -1;
            $lastB = $b['points'][count($b['points']) - 1]['avg_rating'] ?? -1;
            return $lastB <=> $lastA;
        });

        return [
            'months' => array_map(static fn ($a) => ['key' => $a['key'], 'label' => $a['label']], $anchors),
            'rows' => $resultRows,
            'pool' => $poolPerMonth,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDoctorTrend(int $branchId, int $monthsBack): array
    {
        $branchName = DB::table('locations')
            ->where('id', $branchId)
            ->whereNull('deleted_at')
            ->value('name');

        if ($branchName === null) {
            return $this->emptyDoctorResponse($branchId, null);
        }

        [$anchors, $monthKeys, $windowStart, $windowEnd] = $this->buildWindow($monthsBack);

        $rows = DB::table('feedback')
            ->join('appointments', 'feedback.appointment_id', '=', 'appointments.id')
            ->join('users as u', 'appointments.doctor_id', '=', 'u.id')
            ->where('appointments.location_id', $branchId)
            ->where('appointments.appointment_type_id', self::TREATMENT_TYPE_ID)
            ->where('appointments.appointment_status_id', self::TREATMENT_STATUS_ID)
            ->whereBetween('appointments.scheduled_date', [$windowStart, $windowEnd])
            ->whereNull('appointments.deleted_at')
            ->where('u.active', 1)
            ->select('appointments.doctor_id as doctor_id')
            ->selectRaw("DATE_FORMAT(appointments.scheduled_date, '%Y-%m') AS ym")
            ->selectRaw('AVG(CAST(feedback.rating AS DECIMAL(4,2))) AS avg_rating')
            ->selectRaw('COUNT(DISTINCT feedback.appointment_id) AS total_feedback')
            ->groupBy('appointments.doctor_id')
            ->groupByRaw("DATE_FORMAT(appointments.scheduled_date, '%Y-%m')")
            ->get();

        $perDoctor = [];
        foreach ($rows as $row) {
            $did = (int) $row->doctor_id;
            if ($did <= 0) {
                continue;
            }
            if (! isset($perDoctor[$did])) {
                $perDoctor[$did] = [];
                foreach ($monthKeys as $k) {
                    $perDoctor[$did][$k] = ['avg' => null, 'total' => 0];
                }
            }
            $perDoctor[$did][(string) $row->ym] = [
                'avg' => $row->avg_rating !== null ? round((float) $row->avg_rating, 2) : null,
                'total' => (int) $row->total_feedback,
            ];
        }

        $doctorIds = array_keys($perDoctor);
        $names = $doctorIds === []
            ? []
            : DB::table('users')
                ->whereIn('id', $doctorIds)
                ->where('active', 1)
                ->pluck('name', 'id')
                ->toArray();

        $resultRows = [];
        foreach ($perDoctor as $did => $monthly) {
            if (! isset($names[$did])) {
                continue;
            }
            $points = [];
            foreach ($monthKeys as $key) {
                $points[] = [
                    'month' => $key,
                    'avg_rating' => $monthly[$key]['avg'],
                    'total_feedback' => $monthly[$key]['total'],
                ];
            }
            $resultRows[] = [
                'doctor_id' => $did,
                'doctor_name' => $names[$did],
                'points' => $points,
            ];
        }

        // Branch-level pool: same query without the doctor join, so that
        // it reflects the entire branch (including any feedback whose
        // doctor row is now inactive — the row still happened).
        $branchPoolRows = DB::table('feedback')
            ->join('appointments', 'feedback.appointment_id', '=', 'appointments.id')
            ->where('appointments.location_id', $branchId)
            ->where('appointments.appointment_type_id', self::TREATMENT_TYPE_ID)
            ->where('appointments.appointment_status_id', self::TREATMENT_STATUS_ID)
            ->whereBetween('appointments.scheduled_date', [$windowStart, $windowEnd])
            ->whereNull('appointments.deleted_at')
            ->selectRaw("DATE_FORMAT(appointments.scheduled_date, '%Y-%m') AS ym")
            ->selectRaw('AVG(CAST(feedback.rating AS DECIMAL(4,2))) AS avg_rating')
            ->selectRaw('COUNT(DISTINCT feedback.appointment_id) AS total_feedback')
            ->groupByRaw("DATE_FORMAT(appointments.scheduled_date, '%Y-%m')")
            ->get();

        $branchPoolByMonth = [];
        foreach ($branchPoolRows as $row) {
            $branchPoolByMonth[(string) $row->ym] = [
                'avg' => $row->avg_rating !== null ? round((float) $row->avg_rating, 2) : null,
                'total' => (int) $row->total_feedback,
            ];
        }

        $branchPool = [];
        foreach ($monthKeys as $key) {
            $bucket = $branchPoolByMonth[$key] ?? ['avg' => null, 'total' => 0];
            $branchPool[] = [
                'month' => $key,
                'avg_rating' => $bucket['avg'],
                'total_feedback' => $bucket['total'],
            ];
        }

        usort($resultRows, function (array $a, array $b): int {
            $lastA = $a['points'][count($a['points']) - 1]['avg_rating'] ?? -1;
            $lastB = $b['points'][count($b['points']) - 1]['avg_rating'] ?? -1;
            return $lastB <=> $lastA;
        });

        return [
            'branch_id' => $branchId,
            'branch_name' => $branchName,
            'months' => array_map(static fn ($a) => ['key' => $a['key'], 'label' => $a['label']], $anchors),
            'rows' => $resultRows,
            'branch_pool' => $branchPool,
        ];
    }

    /**
     * Build the 6-month rolling window. Returns:
     *   [anchors[], monthKeys[], windowStartDate, windowEndDate]
     *
     * `windowEndDate` excludes today (matches BranchFeedbackMetric).
     *
     * @return array{0: list<array{key:string,label:string}>, 1: list<string>, 2: string, 3: string}
     */
    private function buildWindow(int $monthsBack): array
    {
        $today = CarbonImmutable::now();
        $anchors = [];
        for ($i = $monthsBack - 1; $i >= 0; $i--) {
            $monthStart = $today->subMonths($i)->startOfMonth();
            $anchors[] = [
                'key' => $monthStart->format('Y-m'),
                'label' => $monthStart->format('M Y'),
            ];
        }
        $monthKeys = array_map(static fn ($a) => $a['key'], $anchors);

        $windowStart = $today->subMonths($monthsBack - 1)->startOfMonth()->toDateString();
        $endOfThisMonth = $today->endOfMonth();
        $rawEnd = $endOfThisMonth->greaterThan($today) ? $today : $endOfThisMonth;
        $windowEnd = $rawEnd->isToday() ? $today->subDay()->toDateString() : $rawEnd->toDateString();

        return [$anchors, $monthKeys, $windowStart, $windowEnd];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyDoctorResponse(int $branchId, ?string $branchName): array
    {
        return [
            'branch_id' => $branchId,
            'branch_name' => $branchName,
            'months' => [],
            'rows' => [],
            'branch_pool' => [],
        ];
    }
}
