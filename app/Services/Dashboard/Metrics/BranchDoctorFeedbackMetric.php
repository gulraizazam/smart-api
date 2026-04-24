<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Metrics;

use App\Services\Dashboard\ValueObjects\DateRange;
use App\Services\Dashboard\ValueObjects\MetricScope;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Doctor-wise feedback drill-down for a single branch — used by the
 * Branch Feedback Score panel when the user clicks a bar.
 *
 * Mirrors BranchFeedbackMetric's definitions so numbers line up with the
 * parent chart: feedback set = feedback on arrived treatment appointments
 * (appointment_type_id=2, appointment_status_id=2) scheduled in the
 * window, with today excluded.
 *
 * Return shape: {
 *   branch_id, branch_name,
 *   rows: [{ doctor_id, doctor_name, avg_rating, total_feedback,
 *            treatment_count, coverage_pct }],
 *   coverage: { total_treatments, total_treatment_feedback, percentage }
 * }
 */
final class BranchDoctorFeedbackMetric
{
    private const TREATMENT_TYPE_ID = 2;

    private const TREATMENT_STATUS_ID = 2;

    private const CACHE_TTL = 300;

    /**
     * @return array<string, mixed>
     */
    public function compute(MetricScope $scope, DateRange $range, int $branchId): array
    {
        if ($scope->isDenyAll()) {
            return $this->emptyResponse($branchId, null);
        }

        if ($scope->isBranchScoped() && $scope->branchIds !== null && ! in_array($branchId, $scope->branchIds, true)) {
            // Silent-drop would leak which IDs are valid; mirror the controller's
            // validation-error shape by returning an empty response + null name.
            return $this->emptyResponse($branchId, null);
        }

        $cacheKey = 'mgmt_dash:branch_doctor_feedback:'
            .$scope->cacheKey()
            .'|'.$range->startString().'..'.$range->endString()
            .'|b='.$branchId;

        return Cache::remember(
            $cacheKey,
            self::CACHE_TTL,
            fn () => $this->build($scope, $range, $branchId),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function build(MetricScope $scope, DateRange $range, int $branchId): array
    {
        $branchName = DB::table('locations')
            ->where('id', $branchId)
            ->whereNull('deleted_at')
            ->value('name');

        if ($branchName === null) {
            return $this->emptyResponse($branchId, null);
        }

        $endDate = $this->excludeToday($range->endString());

        $feedbackRows = DB::table('feedback')
            ->join('appointments', 'feedback.appointment_id', '=', 'appointments.id')
            ->where('appointments.location_id', $branchId)
            ->where('appointments.appointment_type_id', self::TREATMENT_TYPE_ID)
            ->where('appointments.appointment_status_id', self::TREATMENT_STATUS_ID)
            ->whereBetween('appointments.scheduled_date', [$range->startString(), $endDate])
            ->whereNull('appointments.deleted_at')
            ->select('appointments.doctor_id as doctor_id')
            ->selectRaw('AVG(CAST(feedback.rating AS DECIMAL(4,2))) AS avg_rating, COUNT(DISTINCT feedback.appointment_id) AS total_feedback')
            ->groupBy('appointments.doctor_id')
            ->get();

        $treatmentRows = DB::table('appointments')
            ->where('location_id', $branchId)
            ->where('appointment_type_id', self::TREATMENT_TYPE_ID)
            ->where('appointment_status_id', self::TREATMENT_STATUS_ID)
            ->whereBetween('scheduled_date', [$range->startString(), $endDate])
            ->whereNull('deleted_at')
            ->select('doctor_id')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('doctor_id')
            ->get();

        $feedbackByDoctor = [];
        foreach ($feedbackRows as $row) {
            $feedbackByDoctor[(int) $row->doctor_id] = [
                'avg' => $row->avg_rating !== null ? (float) $row->avg_rating : 0.0,
                'total' => (int) $row->total_feedback,
            ];
        }
        $treatmentsByDoctor = [];
        foreach ($treatmentRows as $row) {
            $treatmentsByDoctor[(int) $row->doctor_id] = (int) $row->total;
        }

        $doctorIds = array_values(array_unique(array_merge(
            array_keys($feedbackByDoctor),
            array_keys($treatmentsByDoctor),
        )));

        $doctorIds = array_filter($doctorIds, static fn ($id) => $id > 0);

        $doctorNames = $doctorIds === []
            ? []
            : DB::table('users')
                ->whereIn('id', $doctorIds)
                ->pluck('name', 'id')
                ->toArray();

        $rows = [];
        $totalTreatments = 0;
        $totalFeedback = 0;
        foreach ($doctorIds as $doctorId) {
            $fb = $feedbackByDoctor[$doctorId] ?? ['avg' => 0.0, 'total' => 0];
            $tCount = $treatmentsByDoctor[$doctorId] ?? 0;
            $totalTreatments += $tCount;
            $totalFeedback += $fb['total'];
            $rows[] = [
                'doctor_id' => $doctorId,
                'doctor_name' => $doctorNames[$doctorId] ?? "Doctor #{$doctorId}",
                'avg_rating' => round($fb['avg'], 2),
                'total_feedback' => $fb['total'],
                'treatment_count' => $tCount,
                'coverage_pct' => $tCount > 0 ? round(($fb['total'] / $tCount) * 100, 1) : null,
            ];
        }

        usort($rows, static fn (array $a, array $b): int => $b['avg_rating'] <=> $a['avg_rating']);

        return [
            'branch_id' => $branchId,
            'branch_name' => $branchName,
            'rows' => $rows,
            'coverage' => [
                'total_treatments' => $totalTreatments,
                'total_treatment_feedback' => $totalFeedback,
                'percentage' => $totalTreatments > 0
                    ? round(($totalFeedback / $totalTreatments) * 100, 1)
                    : null,
            ],
        ];
    }

    private function excludeToday(string $endDate): string
    {
        $today = now()->format('Y-m-d');

        return $endDate >= $today
            ? now()->subDay()->format('Y-m-d')
            : $endDate;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyResponse(int $branchId, ?string $branchName): array
    {
        return [
            'branch_id' => $branchId,
            'branch_name' => $branchName,
            'rows' => [],
            'coverage' => [
                'total_treatments' => 0,
                'total_treatment_feedback' => 0,
                'percentage' => null,
            ],
        ];
    }
}
