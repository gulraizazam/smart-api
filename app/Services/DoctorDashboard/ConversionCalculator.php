<?php

declare(strict_types=1);
namespace App\Services\DoctorDashboard;

use App\Helpers\DoctorDashboardHelper;
use App\Services\Conversion\ConversionService;
use Illuminate\Support\Facades\DB;

class ConversionCalculator
{
    /**
     * Calculate conversion rate for a doctor in a date range.
     *
     * Uses the shared ConversionService which validates:
     * - Invoice exists for appointment
     * - Service added on/after invoice date
     * - First payment on/after invoice date falls within date range
     * - Conversion spend calculated via genericfunctionforstaffwiserevenue
     *
     * @param int $doctorId
     * @param string $startDate Y-m-d
     * @param string $endDate Y-m-d
     * @param int $accountId
     * @return array
     */
    public function calculate(int $doctorId, string $startDate, string $endDate, int $accountId): array
    {
        $conversionService = app(ConversionService::class);
        $result = $conversionService->calculateForDoctor($doctorId, $startDate, $endDate, $accountId);

        return [
            'total_arrived' => $result['total_arrived'],
            'total_converted' => $result['total_converted'],
            'conversion_rate' => $result['conversion_rate'],
        ];
    }

    /**
     * Calculate weekly conversion rate for streak calculation.
     * Returns conversion rate for a specific week (Sunday to Saturday).
     *
     * @param int $doctorId
     * @param string $weekStart Y-m-d (Sunday)
     * @param string $weekEnd Y-m-d (Saturday)
     * @param int $accountId
     * @return array|null null if no consultations in the week
     */
    public function calculateWeekly(int $doctorId, string $weekStart, string $weekEnd, int $accountId): ?array
    {
        $result = $this->calculate($doctorId, $weekStart, $weekEnd, $accountId);

        // No consultations this week = null (streak pauses)
        if ($result['total_arrived'] === 0) {
            return null;
        }

        return $result;
    }

    /**
     * Get conversion data for multiple doctors (benchmark calculation).
     * Only includes doctors with minimum consultation threshold.
     *
     * @param array $doctorIds
     * @param string $startDate
     * @param string $endDate
     * @param int $accountId
     * @param int $minConsultations
     * @return array [doctorId => ['conversion_rate' => float, ...]]
     */
    public function calculateForDoctors(array $doctorIds, string $startDate, string $endDate, int $accountId, int $minConsultations = 5): array
    {
        if (empty($doctorIds)) {
            return [];
        }

        $results = [];
        $conversionService = app(ConversionService::class);

        foreach ($doctorIds as $docId) {
            $result = $conversionService->calculateForDoctor($docId, $startDate, $endDate, $accountId);

            // Only include doctors meeting minimum consultation threshold
            if ($result['total_arrived'] >= $minConsultations) {
                $results[$docId] = [
                    'total_arrived' => $result['total_arrived'],
                    'total_converted' => $result['total_converted'],
                    'conversion_rate' => $result['conversion_rate'],
                ];
            }
        }

        return $results;
    }

    /**
     * @return array
     */
    private function emptyResult(): array
    {
        return [
            'total_arrived' => 0,
            'total_converted' => 0,
            'conversion_rate' => 0,
        ];
    }
}
