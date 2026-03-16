<?php

namespace App\Services\DoctorDashboard;

use App\Helpers\DoctorDashboardHelper;
use Illuminate\Support\Facades\DB;

class RevenueCalculator
{
    /**
     * Calculate total revenue for a doctor in a date range.
     *
     * Algorithm (Last Consultant Attribution):
     * 1. Find all patients where this doctor was the LAST consulting doctor
     *    (most recent arrived consultation, appointment_type_id=1)
     * 2. Sum all package_advances.cash_amount for those patients within date range
     * 3. Does NOT include product sales revenue
     *
     * @param int $doctorId
     * @param string $startDate Y-m-d
     * @param string $endDate Y-m-d
     * @param int $accountId
     * @return array
     */
    public function calculate(int $doctorId, string $startDate, string $endDate, int $accountId): array
    {
        $patientIds = $this->getDoctorPatientPool($doctorId, $accountId);

        if (empty($patientIds)) {
            return $this->emptyResult();
        }

        // Sum all payments for these patients within date range
        $totalRevenue = DB::table('package_advances as pa')
            ->join('packages as p', 'pa.package_id', '=', 'p.id')
            ->whereIn('p.patient_id', $patientIds)
            ->where('pa.cash_amount', '>', 0)
            ->whereBetween('pa.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->sum('pa.cash_amount');

        return [
            'total_revenue' => round((float) $totalRevenue, 2),
            'patient_count' => count($patientIds),
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
        $arrivedStatusId = DoctorDashboardHelper::getArrivedStatusId($accountId);
        $convertedStatusId = DoctorDashboardHelper::getConvertedStatusId($accountId);

        if (!$arrivedStatusId) {
            return [];
        }

        $statusIds = array_filter([$arrivedStatusId, $convertedStatusId]);

        // Find each patient's last consulting doctor using a subquery
        // For each patient, get the consultation with the latest scheduled_date + scheduled_time
        // The doctor_id on that consultation = the "owning" doctor
        return DB::table('appointments as a')
            ->joinSub(
                DB::table('appointments')
                    ->where('appointment_type_id', 1)
                    ->whereIn('base_appointment_status_id', $statusIds)
                    ->select('patient_id', DB::raw('MAX(CONCAT(scheduled_date, " ", COALESCE(scheduled_time, "00:00:00"))) as max_datetime'))
                    ->groupBy('patient_id'),
                'latest',
                function ($join) {
                    $join->on('a.patient_id', '=', 'latest.patient_id')
                        ->whereRaw('CONCAT(a.scheduled_date, " ", COALESCE(a.scheduled_time, "00:00:00")) = latest.max_datetime');
                }
            )
            ->where('a.appointment_type_id', 1)
            ->whereIn('a.base_appointment_status_id', $statusIds)
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
     * Calculate revenue grouped by location for a doctor's patient pool.
     * Used to determine the most active branch.
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
        $patientIds = $this->getDoctorPatientPool($doctorId, $accountId);

        if (empty($patientIds) || empty($locationIds)) {
            return [];
        }

        return DB::table('package_advances as pa')
            ->join('packages as p', 'pa.package_id', '=', 'p.id')
            ->join('appointments as a', function ($join) {
                $join->on('pa.appointment_id', '=', 'a.id');
            })
            ->whereIn('p.patient_id', $patientIds)
            ->whereIn('a.location_id', $locationIds)
            ->where('pa.cash_amount', '>', 0)
            ->whereBetween('pa.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->select('a.location_id', DB::raw('SUM(pa.cash_amount) as total_revenue'))
            ->groupBy('a.location_id')
            ->pluck('total_revenue', 'a.location_id')
            ->map(fn($v) => round((float) $v, 2))
            ->toArray();
    }

    /**
     * Calculate revenue for multiple doctors (benchmark).
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

        $arrivedStatusId = DoctorDashboardHelper::getArrivedStatusId($accountId);
        $convertedStatusId = DoctorDashboardHelper::getConvertedStatusId($accountId);

        if (!$arrivedStatusId) {
            return [];
        }

        $statusIds = array_filter([$arrivedStatusId, $convertedStatusId]);

        // Build the patient pool for each doctor using a single efficient query
        // Get the last consultation per patient, then group patients by their owning doctor
        $patientOwners = DB::table('appointments as a')
            ->joinSub(
                DB::table('appointments')
                    ->where('appointment_type_id', 1)
                    ->whereIn('base_appointment_status_id', $statusIds)
                    ->select('patient_id', DB::raw('MAX(CONCAT(scheduled_date, " ", COALESCE(scheduled_time, "00:00:00"))) as max_datetime'))
                    ->groupBy('patient_id'),
                'latest',
                function ($join) {
                    $join->on('a.patient_id', '=', 'latest.patient_id')
                        ->whereRaw('CONCAT(a.scheduled_date, " ", COALESCE(a.scheduled_time, "00:00:00")) = latest.max_datetime');
                }
            )
            ->where('a.appointment_type_id', 1)
            ->whereIn('a.base_appointment_status_id', $statusIds)
            ->whereIn('a.doctor_id', $doctorIds)
            ->select('a.doctor_id', 'a.patient_id')
            ->distinct()
            ->get()
            ->groupBy('doctor_id');

        $results = [];
        foreach ($doctorIds as $docId) {
            $patients = $patientOwners->get($docId);
            if (!$patients || $patients->isEmpty()) {
                $results[$docId] = 0;
                continue;
            }

            $patientIds = $patients->pluck('patient_id')->toArray();

            $revenue = DB::table('package_advances as pa')
                ->join('packages as p', 'pa.package_id', '=', 'p.id')
                ->whereIn('p.patient_id', $patientIds)
                ->where('pa.cash_amount', '>', 0)
                ->whereBetween('pa.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->sum('pa.cash_amount');

            $results[$docId] = round((float) $revenue, 2);
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
