<?php

namespace App\Services\DoctorDashboard;

use App\Helpers\DoctorDashboardHelper;
use Illuminate\Support\Facades\DB;

class ConversionCalculator
{
    /**
     * Calculate conversion rate for a doctor in a date range.
     *
     * Conversion Rate = Converted Consultations / Total Arrived Consultations × 100
     * Converted = arrived consultation (apt_type=1) with package_advances.cash_amount > 0
     *
     * @param int $doctorId
     * @param string $startDate Y-m-d
     * @param string $endDate Y-m-d
     * @param int $accountId
     * @return array
     */
    public function calculate(int $doctorId, string $startDate, string $endDate, int $accountId): array
    {
        $arrivedStatusId = DoctorDashboardHelper::getArrivedStatusId($accountId);
        $convertedStatusId = DoctorDashboardHelper::getConvertedStatusId($accountId);

        if (!$arrivedStatusId) {
            return $this->emptyResult();
        }

        // Total arrived consultations for this doctor in date range
        $statusIds = array_filter([$arrivedStatusId, $convertedStatusId]);

        $totalArrivedConsultations = DB::table('appointments')
            ->where('doctor_id', $doctorId)
            ->where('appointment_type_id', 1)
            ->whereIn('base_appointment_status_id', $statusIds)
            ->whereBetween('scheduled_date', [$startDate, $endDate])
            ->count();

        if ($totalArrivedConsultations === 0) {
            return $this->emptyResult();
        }

        // Converted consultations = arrived consultations where patient made a payment
        $convertedConsultations = DB::table('appointments as a')
            ->join('package_advances as pa', 'pa.appointment_id', '=', 'a.id')
            ->where('a.doctor_id', $doctorId)
            ->where('a.appointment_type_id', 1)
            ->whereIn('a.base_appointment_status_id', $statusIds)
            ->whereBetween('a.scheduled_date', [$startDate, $endDate])
            ->where('pa.cash_amount', '>', 0)
            ->distinct('a.id')
            ->count('a.id');

        $conversionRate = ($convertedConsultations / $totalArrivedConsultations) * 100;

        return [
            'total_arrived' => $totalArrivedConsultations,
            'total_converted' => $convertedConsultations,
            'conversion_rate' => round($conversionRate, 1),
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
        $arrivedStatusId = DoctorDashboardHelper::getArrivedStatusId($accountId);
        $convertedStatusId = DoctorDashboardHelper::getConvertedStatusId($accountId);

        if (!$arrivedStatusId || empty($doctorIds)) {
            return [];
        }

        $statusIds = array_filter([$arrivedStatusId, $convertedStatusId]);

        // Get arrived counts per doctor
        $arrivedCounts = DB::table('appointments')
            ->whereIn('doctor_id', $doctorIds)
            ->where('appointment_type_id', 1)
            ->whereIn('base_appointment_status_id', $statusIds)
            ->whereBetween('scheduled_date', [$startDate, $endDate])
            ->select('doctor_id', DB::raw('COUNT(*) as total_arrived'))
            ->groupBy('doctor_id')
            ->having('total_arrived', '>=', $minConsultations)
            ->pluck('total_arrived', 'doctor_id')
            ->toArray();

        if (empty($arrivedCounts)) {
            return [];
        }

        // Get converted counts per doctor
        $convertedCounts = DB::table('appointments as a')
            ->join('package_advances as pa', 'pa.appointment_id', '=', 'a.id')
            ->whereIn('a.doctor_id', array_keys($arrivedCounts))
            ->where('a.appointment_type_id', 1)
            ->whereIn('a.base_appointment_status_id', $statusIds)
            ->whereBetween('a.scheduled_date', [$startDate, $endDate])
            ->where('pa.cash_amount', '>', 0)
            ->select('a.doctor_id', DB::raw('COUNT(DISTINCT a.id) as total_converted'))
            ->groupBy('a.doctor_id')
            ->pluck('total_converted', 'a.doctor_id')
            ->toArray();

        $results = [];
        foreach ($arrivedCounts as $docId => $arrived) {
            $converted = $convertedCounts[$docId] ?? 0;
            $results[$docId] = [
                'total_arrived' => $arrived,
                'total_converted' => $converted,
                'conversion_rate' => round(($converted / $arrived) * 100, 1),
            ];
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
