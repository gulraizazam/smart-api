<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Metrics;

use App\Enums\ResourceType;
use App\Services\Dashboard\Contracts\Metric;
use App\Services\Dashboard\Support\DoctorResolver;
use App\Services\Dashboard\ValueObjects\DateRange;
use App\Services\Dashboard\ValueObjects\MetricScope;
use App\Services\DoctorDashboard\UpsellCalculator;
use Illuminate\Support\Facades\DB;

/**
 * Upsell revenue + rate across any scope.
 *
 * Doctor scope → delegates to UpsellCalculator.
 * Branch / company scope → sums via UpsellCalculator::calculateForDoctors
 * (one grouped query), with doctor set resolved through DoctorResolver.
 *
 * Return shape:
 *   upsell_revenue, unique_upsold_patients, unique_treated_patients, upsell_rate
 */
final class UpsellMetric implements Metric
{
    public function __construct(
        private readonly UpsellCalculator $calculator,
        private readonly DoctorResolver $doctors,
    ) {}

    /**
     * @return array{upsell_revenue: float, unique_upsold_patients: int, unique_treated_patients: int, upsell_rate: ?float}
     */
    public function compute(MetricScope $scope, DateRange $range): array
    {
        if ($scope->isDenyAll()) {
            return $this->empty();
        }

        if ($scope->isResourceScoped() && $scope->resourceType === ResourceType::Doctor) {
            return $this->calculator->calculate(
                (int) $scope->resourceId,
                $range->startString(),
                $range->endString(),
                $scope->accountId,
            );
        }

        return $this->aggregateAcrossDoctors($scope, $range);
    }

    private function aggregateAcrossDoctors(MetricScope $scope, DateRange $range): array
    {
        $doctorIds = $this->doctors->idsInScope($scope);

        if ($doctorIds === []) {
            return $this->empty();
        }

        $perDoctor = $this->calculator->calculateForDoctors(
            $doctorIds,
            $range->startString(),
            $range->endString(),
            $scope->accountId,
        );

        $upsellRevenue = array_sum($perDoctor);
        $uniqueUpsold = $this->sumDistinctPatients($doctorIds, $range, 'upsold');
        $uniqueTreated = $this->sumDistinctPatients($doctorIds, $range, 'treated');

        $rate = null;
        if ($uniqueTreated > 0) {
            $rate = round(($uniqueUpsold / $uniqueTreated) * 100, 1);
        } elseif ($uniqueUpsold === 0) {
            $rate = 0.0;
        }

        return [
            'upsell_revenue' => round($upsellRevenue, 2),
            'unique_upsold_patients' => $uniqueUpsold,
            'unique_treated_patients' => $uniqueTreated,
            'upsell_rate' => $rate,
        ];
    }

    /**
     * @param  list<int>  $doctorIds
     */
    private function sumDistinctPatients(array $doctorIds, DateRange $range, string $mode): int
    {
        $start = $range->startString().' 00:00:00';
        $end = $range->endString().' 23:59:59';

        if ($mode === 'upsold') {
            return (int) DB::table('package_services as ps')
                ->join('packages as p', 'ps.package_id', '=', 'p.id')
                ->join('appointments as a', 'p.appointment_id', '=', 'a.id')
                ->whereIn('ps.sold_by', $doctorIds)
                ->where(function ($q): void {
                    $q->where('a.appointment_type_id', '!=', 1)
                        ->orWhereColumn('a.doctor_id', '!=', 'ps.sold_by');
                })
                ->whereBetween('ps.created_at', [$start, $end])
                ->distinct('a.patient_id')
                ->count('a.patient_id');
        }

        return (int) DB::table('appointments')
            ->whereIn('doctor_id', $doctorIds)
            ->where('appointment_type_id', 2)
            ->whereBetween('scheduled_date', [$range->startString(), $range->endString()])
            ->distinct('patient_id')
            ->count('patient_id');
    }

    /**
     * @return array{upsell_revenue: float, unique_upsold_patients: int, unique_treated_patients: int, upsell_rate: float}
     */
    private function empty(): array
    {
        return [
            'upsell_revenue' => 0.0,
            'unique_upsold_patients' => 0,
            'unique_treated_patients' => 0,
            'upsell_rate' => 0.0,
        ];
    }
}
