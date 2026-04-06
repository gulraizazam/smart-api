<?php

declare(strict_types=1);
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
        $treatmentStatusIds = DoctorDashboardHelper::getTreatmentStatusIds();

        // Get all arrived treatments by this doctor in the date range
        $treatments = DB::table('appointments')
            ->where('doctor_id', $doctorId)
            ->where('appointment_type_id', 2)
            ->whereIn('appointment_status_id', $treatmentStatusIds)
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
                    ->where('doctor_id', $doctorId)
                    ->where('appointment_type_id', 2)
                    ->whereIn('appointment_status_id', $treatmentStatusIds)
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
     * Calculate avg procedures per converted patient (trailing 3 months).
     *
     * 1. Find patients this doctor converted in the trailing 3 months
     *    (consultation with converted status, doctor_id = this doctor)
     * 2. Count arrived treatment appointments each of those patients completed
     *    from their conversion date up to today
     * 3. Average = total treatments / total converted patients
     *
     * @param int $doctorId
     * @param int $accountId
     * @return array
     */
    public function calculateAvgProcedures(int $doctorId, int $accountId): array
    {
        $convertedStatusId = DoctorDashboardHelper::getConvertedStatusId($accountId);
        $treatmentStatusIds = DoctorDashboardHelper::getTreatmentStatusIds();

        if (!$convertedStatusId) {
            return ['avg_procedures' => 0, 'total_procedures' => 0, 'converted_patients' => 0];
        }

        $trailing3Start = \Carbon\Carbon::now()->subMonths(3)->startOfMonth()->format('Y-m-d');
        $today = \Carbon\Carbon::now()->format('Y-m-d');

        // Get patients converted by this doctor in the trailing 3 months
        // For each, get the earliest conversion date (in case of multiple consultations)
        $conversions = DB::table('appointments')
            ->where('doctor_id', $doctorId)
            ->where('appointment_type_id', 1)
            ->where('appointment_status_id', $convertedStatusId)
            ->whereBetween('scheduled_date', [$trailing3Start, $today])
            ->select('patient_id', DB::raw('MIN(scheduled_date) as conversion_date'))
            ->groupBy('patient_id')
            ->get();

        if ($conversions->isEmpty()) {
            return ['avg_procedures' => 0, 'total_procedures' => 0, 'converted_patients' => 0];
        }

        $totalProcedures = 0;

        foreach ($conversions as $conv) {
            // Count arrived treatments for this patient from conversion date onwards
            $procedures = DB::table('appointments')
                ->where('patient_id', $conv->patient_id)
                ->where('appointment_type_id', 2)
                ->whereIn('appointment_status_id', $treatmentStatusIds)
                ->where('scheduled_date', '>=', $conv->conversion_date)
                ->where('scheduled_date', '<=', $today)
                ->count();

            $totalProcedures += $procedures;
        }

        $convertedPatients = $conversions->count();

        return [
            'avg_procedures' => $convertedPatients > 0 ? round($totalProcedures / $convertedPatients, 1) : 0,
            'total_procedures' => $totalProcedures,
            'converted_patients' => $convertedPatients,
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
