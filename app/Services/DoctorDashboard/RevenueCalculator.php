<?php

declare(strict_types=1);
namespace App\Services\DoctorDashboard;

use App\Helpers\DoctorDashboardHelper;
use App\Services\Conversion\ConversionService;
use Illuminate\Support\Facades\DB;

class RevenueCalculator
{
    public function __construct(
        private readonly ConversionService $conversionService,
    ) {}
    /**
     * Calculate total revenue for a doctor in a date range.
     *
     * FIXED: Now uses ConversionService to match conversion report logic.
     * Only counts validated conversion spend (not all payments from patient pool).
     *
     * Algorithm:
     * 1. Get all arrived/converted consultations for this doctor with payments in date range
     * 2. Validate each conversion using ConversionService criteria:
     *    - Invoice exists for appointment
     *    - Service added on or after invoice date
     *    - First payment on or after invoice date falls within date range
     * 3. Sum only validated conversion spend (revenue_in - refund_out)
     * 4. Does NOT include product sales revenue
     *
     * @param int $doctorId
     * @param string $startDate Y-m-d
     * @param string $endDate Y-m-d
     * @param int $accountId
     * @return array
     */
    public function calculate(int $doctorId, string $startDate, string $endDate, int $accountId): array
    {
        // Use ConversionService to get validated conversion spend
        $result = $this->conversionService->calculateForDoctor($doctorId, $startDate, $endDate, $accountId);

        return [
            'total_revenue' => $result['total_spend'],
            'patient_count' => $result['total_converted'],
        ];
    }

    /**
     * Get the "patient pool" for a doctor — all patients where this doctor
     * is the most recent consulting doctor (last arrived consultation).
     *
     * @param int $doctorId
     * @param int $accountId
     * @return array Patient IDs
     */
    public function getDoctorPatientPool(int $doctorId, int $accountId): array
    {
        $consultationStatusIds = DoctorDashboardHelper::getConsultationStatusIds();

        // Find each patient's last consulting doctor using a subquery
        // For each patient, get the consultation with the latest scheduled_date + scheduled_time
        // The doctor_id on that consultation = the "owning" doctor
        return DB::table('appointments as a')
            ->joinSub(
                DB::table('appointments')
                    ->where('appointment_type_id', 1)
                    ->whereIn('appointment_status_id', $consultationStatusIds)
                    ->select('patient_id', DB::raw('MAX(CONCAT(scheduled_date, " ", COALESCE(scheduled_time, "00:00:00"))) as max_datetime'))
                    ->groupBy('patient_id'),
                'latest',
                function ($join) {
                    $join->on('a.patient_id', '=', 'latest.patient_id')
                        ->whereRaw('CONCAT(a.scheduled_date, " ", COALESCE(a.scheduled_time, "00:00:00")) = latest.max_datetime');
                }
            )
            ->where('a.appointment_type_id', 1)
            ->whereIn('a.appointment_status_id', $consultationStatusIds)
            ->where('a.doctor_id', $doctorId)
            ->distinct()
            ->pluck('a.patient_id')
            ->toArray();
    }

    /**
     * Calculate average client value.
     * Avg Client Value = Total Revenue / Converted Consultations
     *
     * @param float $totalRevenue
     * @param int $convertedConsultations
     * @return float
     */
    public function calculateAvgClientValue(float $totalRevenue, int $convertedConsultations): float
    {
        if ($convertedConsultations === 0) {
            return 0;
        }

        return round($totalRevenue / $convertedConsultations, 2);
    }

    /**
     * Calculate revenue grouped by location for a doctor.
     * Used to determine the most active branch.
     *
     * FIXED: Now uses validated conversion spend per location.
     *
     * @param int $doctorId
     * @param string $startDate
     * @param string $endDate
     * @param int $accountId
     * @param array $locationIds
     * @return array [locationId => revenue]
     */
    public function calculateByLocation(int $doctorId, string $startDate, string $endDate, int $accountId, array $locationIds): array
    {
        if (empty($locationIds)) {
            return [];
        }

        $arrivedStatusId = DoctorDashboardHelper::getArrivedStatusId($accountId);
        $convertedStatusId = DoctorDashboardHelper::getConvertedStatusId($accountId);

        if (!$arrivedStatusId) {
            return [];
        }

        $revenueByLocation = [];

        // Calculate validated conversion spend for each location
        foreach ($locationIds as $locationId) {
            $candidates = $this->conversionService->fetchCandidateAppointments(
                [$doctorId],
                [$locationId],
                $arrivedStatusId,
                $convertedStatusId,
                $startDate,
                $endDate
            );

            if ($candidates->isEmpty()) {
                $revenueByLocation[$locationId] = 0;
                continue;
            }

            $result = $this->conversionService->getValidatedConversions($candidates, $startDate, $endDate);
            $revenueByLocation[$locationId] = round($result['total_spend'], 2);
        }

        return $revenueByLocation;
    }

    /**
     * Calculate revenue for multiple doctors (benchmark).
     *
     * FIXED: Now uses validated conversion spend for each doctor.
     *
     * @param array $doctorIds
     * @param string $startDate
     * @param string $endDate
     * @param int $accountId
     * @return array [doctorId => total_revenue]
     */
    public function calculateForDoctors(array $doctorIds, string $startDate, string $endDate, int $accountId): array
    {
        if (empty($doctorIds)) {
            return [];
        }

        $results = [];
        foreach ($doctorIds as $doctorId) {
            $result = $this->conversionService->calculateForDoctor($doctorId, $startDate, $endDate, $accountId);
            $results[$doctorId] = $result['total_spend'];
        }

        return $results;
    }

    /**
     * @return array
     */
    private function emptyResult(): array
    {
        return [
            'total_revenue' => 0,
            'patient_count' => 0,
        ];
    }
}
