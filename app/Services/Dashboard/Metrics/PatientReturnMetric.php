<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Metrics;

use App\Enums\ResourceType;
use App\Helpers\DoctorDashboardHelper;
use App\Services\Dashboard\Contracts\Metric;
use App\Services\Dashboard\Support\DoctorResolver;
use App\Services\Dashboard\ValueObjects\DateRange;
use App\Services\Dashboard\ValueObjects\MetricScope;
use App\Services\DoctorDashboard\PatientReturnCalculator;
use Illuminate\Support\Facades\DB;

/**
 * Patient return rate (45-day window) + avg procedures per converted patient
 * across any scope.
 *
 * Doctor scope → PatientReturnCalculator (byte-identical).
 * Branch / company scope → aggregates uniquely-treated patients + those with
 * a return treatment within 45 days. Rate = returned / unique.
 */
final class PatientReturnMetric implements Metric
{
    public function __construct(
        private readonly PatientReturnCalculator $calculator,
        private readonly DoctorResolver $doctors,
    ) {}

    /**
     * @return array{total_unique_patients: int, patients_returned: int, return_rate: float}
     */
    public function compute(MetricScope $scope, DateRange $range): array
    {
        if ($scope->isDenyAll()) {
            return $this->empty();
        }

        if ($scope->isResourceScoped() && $scope->resourceType === ResourceType::Doctor) {
            $result = $this->calculator->calculate(
                (int) $scope->resourceId,
                $range->startString(),
                $range->endString(),
                $scope->accountId,
            );

            return [
                'total_unique_patients' => (int) $result['total_unique_patients'],
                'patients_returned' => (int) $result['patients_returned'],
                'return_rate' => (float) $result['return_rate'],
            ];
        }

        return $this->aggregate($scope, $range);
    }

    /**
     * Same shape as {@see compute()} but reports the "any-doctor" return
     * rate — did this doctor's treated patients come back to *anyone* in
     * the network within 45 days? Higher than `compute()`'s same-doctor
     * rate ⇒ patient leak.
     *
     * Doctor scope only: branch / company aggregation isn't meaningful for
     * this signal (the existing aggregate query already excludes the
     * doctor filter); falls back to {@see aggregate()} for those scopes.
     *
     * @return array{total_unique_patients: int, patients_returned: int, return_rate: float}
     */
    public function computeAnyDoctor(MetricScope $scope, DateRange $range): array
    {
        if ($scope->isDenyAll()) {
            return $this->empty();
        }

        if ($scope->isResourceScoped() && $scope->resourceType === ResourceType::Doctor) {
            $result = $this->calculator->calculateAnyDoctor(
                (int) $scope->resourceId,
                $range->startString(),
                $range->endString(),
                $scope->accountId,
            );

            return [
                'total_unique_patients' => (int) $result['total_unique_patients'],
                'patients_returned' => (int) $result['patients_returned'],
                'return_rate' => (float) $result['return_rate'],
            ];
        }

        return $this->aggregate($scope, $range);
    }

    private function aggregate(MetricScope $scope, DateRange $range): array
    {
        $treatmentStatusIds = DoctorDashboardHelper::getTreatmentStatusIds();

        $treatmentsQuery = DB::table('appointments')
            ->where('appointment_type_id', 2)
            ->whereIn('appointment_status_id', $treatmentStatusIds)
            ->whereBetween('scheduled_date', [$range->startString(), $range->endString()]);

        if ($scope->isBranchScoped()) {
            $treatmentsQuery->whereIn('location_id', $scope->branchIds);
        }

        $treatments = $treatmentsQuery
            ->select('id', 'patient_id', 'scheduled_date')
            ->get();

        if ($treatments->isEmpty()) {
            return $this->empty();
        }

        $patientTreatments = $treatments->groupBy('patient_id');
        $totalUnique = $patientTreatments->count();
        $returned = 0;

        foreach ($patientTreatments as $patientId => $appts) {
            foreach ($appts as $appt) {
                $windowEnd = date(
                    'Y-m-d',
                    strtotime((string) $appt->scheduled_date.' +'.PatientReturnCalculator::RETURN_WINDOW_DAYS.' days'),
                );

                $returnExists = DB::table('appointments')
                    ->where('patient_id', $patientId)
                    ->where('appointment_type_id', 2)
                    ->whereIn('appointment_status_id', $treatmentStatusIds)
                    ->where('id', '!=', $appt->id)
                    ->where('scheduled_date', '>', $appt->scheduled_date)
                    ->where('scheduled_date', '<=', $windowEnd)
                    ->exists();

                if ($returnExists) {
                    $returned++;
                    break;
                }
            }
        }

        return [
            'total_unique_patients' => $totalUnique,
            'patients_returned' => $returned,
            'return_rate' => $totalUnique > 0 ? round(($returned / $totalUnique) * 100, 1) : 0.0,
        ];
    }

    /**
     * Average procedures per converted patient (trailing 3 months). Doctor-
     * specific by design; branch/company sums across doctors in scope.
     *
     * @return array{avg_procedures: float, total_procedures: int, converted_patients: int}
     */
    public function avgProcedures(MetricScope $scope): array
    {
        if ($scope->isDenyAll()) {
            return ['avg_procedures' => 0.0, 'total_procedures' => 0, 'converted_patients' => 0];
        }

        if ($scope->isResourceScoped() && $scope->resourceType === ResourceType::Doctor) {
            $result = $this->calculator->calculateAvgProcedures(
                (int) $scope->resourceId,
                $scope->accountId,
            );

            return [
                'avg_procedures' => (float) $result['avg_procedures'],
                'total_procedures' => (int) $result['total_procedures'],
                'converted_patients' => (int) $result['converted_patients'],
            ];
        }

        $doctorIds = $this->doctors->idsInScope($scope);
        $totalProcedures = 0;
        $convertedPatients = 0;

        foreach ($doctorIds as $doctorId) {
            $result = $this->calculator->calculateAvgProcedures($doctorId, $scope->accountId);
            $totalProcedures += (int) $result['total_procedures'];
            $convertedPatients += (int) $result['converted_patients'];
        }

        return [
            'avg_procedures' => $convertedPatients > 0
                ? round($totalProcedures / $convertedPatients, 1)
                : 0.0,
            'total_procedures' => $totalProcedures,
            'converted_patients' => $convertedPatients,
        ];
    }

    /**
     * @return array{total_unique_patients: int, patients_returned: int, return_rate: float}
     */
    private function empty(): array
    {
        return ['total_unique_patients' => 0, 'patients_returned' => 0, 'return_rate' => 0.0];
    }
}
