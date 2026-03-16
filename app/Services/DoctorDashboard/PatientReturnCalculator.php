<?php

namespace App\Services\DoctorDashboard;

use App\Helpers\DoctorDashboardHelper;
use Illuminate\Support\Facades\DB;

class PatientReturnCalculator
{
    /**
     * 45-day return window.
     */
    const RETURN_WINDOW_DAYS = 45;

    /**
     * Calculate patient return rate for a doctor in a date range.
     *
     * "Treated" = arrived treatment appointment (type=2) with this doctor.
     * "Return" = any arrived treatment within 45 days from that treatment date (any doctor).
     * Each treatment independently starts a 45-day window.
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

        if (!$arrivedStatusId) {
            return $this->emptyResult();
        }

        // Get all arrived treatments by this doctor in the date range
        $treatments = DB::table('appointments')
            ->where('doctor_id', $doctorId)
            ->where('appointment_type_id', 2)
            ->where('appointment_status_id', $arrivedStatusId)
            ->whereBetween('scheduled_date', [$startDate, $endDate])
            ->select('id', 'patient_id', 'scheduled_date')
            ->get();

        if ($treatments->isEmpty()) {
            return $this->emptyResult();
        }

        $totalTreatments = $treatments->count();
        $returnedCount = 0;

        // For each treatment, check if the patient had another arrived treatment within 45 days
        // Group by patient to avoid duplicate checks
        $patientTreatments = $treatments->groupBy('patient_id');
        $patientsWithReturn = 0;
        $totalUniquePatients = $patientTreatments->count();

        foreach ($patientTreatments as $patientId => $patientAppts) {
            $hasReturn = false;

            foreach ($patientAppts as $treatment) {
                $windowEnd = date('Y-m-d', strtotime($treatment->scheduled_date . ' +' . self::RETURN_WINDOW_DAYS . ' days'));

                // Check if this patient has ANY arrived treatment within the 45-day window
                // (by any doctor, not just this doctor)
                $returnExists = DB::table('appointments')
                    ->where('patient_id', $patientId)
                    ->where('appointment_type_id', 2)
                    ->where('appointment_status_id', $arrivedStatusId)
                    ->where('id', '!=', $treatment->id)
                    ->where('scheduled_date', '>', $treatment->scheduled_date)
                    ->where('scheduled_date', '<=', $windowEnd)
                    ->exists();

                if ($returnExists) {
                    $hasReturn = true;
                    break;
                }
            }

            if ($hasReturn) {
                $patientsWithReturn++;
            }
        }

        $returnRate = $totalUniquePatients > 0
            ? round(($patientsWithReturn / $totalUniquePatients) * 100, 1)
            : 0;

        return [
            'total_unique_patients' => $totalUniquePatients,
            'patients_returned' => $patientsWithReturn,
            'return_rate' => $returnRate,
        ];
    }

    /**
     * Calculate avg procedures per patient for a doctor in a date range.
     *
     * Only procedures AFTER a re-consultation count for the new doctor.
     *
     * @param int $doctorId
     * @param string $startDate
     * @param string $endDate
     * @param int $accountId
     * @return array
     */
    public function calculateAvgProcedures(int $doctorId, string $startDate, string $endDate, int $accountId): array
    {
        $arrivedStatusId = DoctorDashboardHelper::getArrivedStatusId($accountId);

        if (!$arrivedStatusId) {
            return ['avg_procedures' => 0, 'total_procedures' => 0, 'unique_patients' => 0];
        }

        $result = DB::table('appointments')
            ->where('doctor_id', $doctorId)
            ->where('appointment_type_id', 2)
            ->where('appointment_status_id', $arrivedStatusId)
            ->whereBetween('scheduled_date', [$startDate, $endDate])
            ->select(
                DB::raw('COUNT(*) as total_procedures'),
                DB::raw('COUNT(DISTINCT patient_id) as unique_patients')
            )
            ->first();

        $totalProcedures = (int) ($result->total_procedures ?? 0);
        $uniquePatients = (int) ($result->unique_patients ?? 0);

        return [
            'avg_procedures' => $uniquePatients > 0 ? round($totalProcedures / $uniquePatients, 1) : 0,
            'total_procedures' => $totalProcedures,
            'unique_patients' => $uniquePatients,
        ];
    }

    /**
     * @return array
     */
    private function emptyResult(): array
    {
        return [
            'total_unique_patients' => 0,
            'patients_returned' => 0,
            'return_rate' => 0,
        ];
    }
}
