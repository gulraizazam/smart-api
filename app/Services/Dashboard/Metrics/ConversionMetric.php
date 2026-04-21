<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Metrics;

use App\Enums\ResourceType;
use App\Services\Conversion\ConversionService;
use App\Services\Dashboard\Contracts\Metric;
use App\Services\Dashboard\Support\DoctorResolver;
use App\Services\Dashboard\ValueObjects\DateRange;
use App\Services\Dashboard\ValueObjects\MetricScope;
use App\Services\DoctorDashboard\ConversionCalculator;

/**
 * Conversion rate across any scope.
 *
 * Doctor scope → delegates to existing ConversionCalculator (validated-
 * conversion-spend semantics — byte-identical to doctor dashboard).
 *
 * Branch / company scope → aggregates ConversionService output across every
 * active doctor in scope (via DoctorResolver). Rate is computed on the summed
 * arrived / converted counts (weighted, not an average-of-rates) so that one
 * low-volume doctor with 0% doesn't drag the company rate disproportionately.
 *
 * Return shape: [
 *   'total_arrived' => int,
 *   'total_converted' => int,
 *   'conversion_rate' => float,
 *   'total_spend' => float,          // revenue tied to converted consultations
 *   'avg_client_value' => float,     // total_spend / total_converted
 * ]
 */
final class ConversionMetric implements Metric
{
    public function __construct(
        private readonly ConversionCalculator $doctorConversion,
        private readonly ConversionService $conversionService,
        private readonly DoctorResolver $doctors,
    ) {}

    /**
     * @return array{total_arrived: int, total_converted: int, conversion_rate: float, total_spend: float, avg_client_value: float}
     */
    public function compute(MetricScope $scope, DateRange $range): array
    {
        if ($scope->isDenyAll()) {
            return $this->empty();
        }

        if ($scope->isResourceScoped() && $scope->resourceType === ResourceType::Doctor) {
            return $this->doctorScoped($scope, $range);
        }

        return $this->aggregateAcrossDoctors($scope, $range);
    }

    private function doctorScoped(MetricScope $scope, DateRange $range): array
    {
        $result = $this->doctorConversion->calculate(
            (int) $scope->resourceId,
            $range->startString(),
            $range->endString(),
            $scope->accountId,
        );

        $totalSpend = (float) ($result['total_spend'] ?? 0);
        $totalConverted = (int) $result['total_converted'];

        return [
            'total_arrived' => (int) $result['total_arrived'],
            'total_converted' => $totalConverted,
            'conversion_rate' => (float) $result['conversion_rate'],
            'total_spend' => round($totalSpend, 2),
            'avg_client_value' => $totalConverted > 0 ? round($totalSpend / $totalConverted, 2) : 0.0,
        ];
    }

    private function aggregateAcrossDoctors(MetricScope $scope, DateRange $range): array
    {
        $doctorIds = $this->doctors->idsInScope($scope);

        if ($doctorIds === []) {
            return $this->empty();
        }

        $totalArrived = 0;
        $totalConverted = 0;
        $totalSpend = 0.0;

        foreach ($doctorIds as $doctorId) {
            $result = $this->conversionService->calculateForDoctor(
                $doctorId,
                $range->startString(),
                $range->endString(),
                $scope->accountId,
            );
            $totalArrived += (int) $result['total_arrived'];
            $totalConverted += (int) $result['total_converted'];
            $totalSpend += (float) ($result['total_spend'] ?? 0);
        }

        $rate = $totalArrived > 0
            ? round(($totalConverted / $totalArrived) * 100, 1)
            : 0.0;

        return [
            'total_arrived' => $totalArrived,
            'total_converted' => $totalConverted,
            'conversion_rate' => $rate,
            'total_spend' => round($totalSpend, 2),
            'avg_client_value' => $totalConverted > 0 ? round($totalSpend / $totalConverted, 2) : 0.0,
        ];
    }

    /**
     * @return array{total_arrived: int, total_converted: int, conversion_rate: float, total_spend: float, avg_client_value: float}
     */
    private function empty(): array
    {
        return [
            'total_arrived' => 0,
            'total_converted' => 0,
            'conversion_rate' => 0.0,
            'total_spend' => 0.0,
            'avg_client_value' => 0.0,
        ];
    }
}
