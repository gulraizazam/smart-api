<?php

declare(strict_types=1);
namespace App\Services\DoctorDashboard;

use App\Enums\AppointmentType;
use App\Helpers\DoctorDashboardHelper;
use Illuminate\Support\Facades\DB;

class UpsellCalculator
{
    /**
     * Calculate upsell revenue and upsell rate for a doctor in a date range.
     *
     * Upselling = services sold by this doctor (sold_by) where sold_by != appointment.doctor_id
     * Upsell Rate = Unique upsold patients / Unique treated patients × 100 (monthly dedup)
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

        // Upsell revenue: services sold by this doctor where the appointment's doctor is different
        // This means the patient was consulting/treated by another doctor, and this doctor sold them a service
        $upsellData = DB::table('package_services as ps')
            ->join('packages as p', 'ps.package_id', '=', 'p.id')
            ->join('appointments as a', 'p.appointment_id', '=', 'a.id')
            ->where('ps.sold_by', $doctorId)
            // Exclude only self-consultation sales (type=1 where this doctor is both seller and consulting doctor)
            ->where(function ($q) use ($doctorId) {
                $q->where('a.appointment_type_id', '!=', AppointmentType::Consultancy->value)
                  ->orWhere('a.doctor_id', '!=', $doctorId);
            })
            ->whereBetween('ps.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->select(
                DB::raw('SUM(ps.tax_including_price) as total_upsell_revenue'),
                DB::raw('COUNT(DISTINCT a.patient_id) as unique_upsold_patients')
            )
            ->first();

        $upsellRevenue = (float) ($upsellData->total_upsell_revenue ?? 0);
        $uniqueUpsoldPatients = (int) ($upsellData->unique_upsold_patients ?? 0);

        // Unique treated patients this month (for upsell rate denominator)
        // Treated = arrived treatment appointments (type=2) where doctor_id = this doctor
        $uniqueTreatedPatients = DB::table('appointments')
            ->where('doctor_id', $doctorId)
            ->where('appointment_type_id', 2)
            ->whereIn('appointment_status_id', $treatmentStatusIds)
            ->whereBetween('scheduled_date', [$startDate, $endDate])
            ->distinct('patient_id')
            ->count('patient_id');

        // Upsell rate calculation with edge case handling
        $upsellRate = null; // null = N/A
        if ($uniqueTreatedPatients > 0) {
            $upsellRate = round(($uniqueUpsoldPatients / $uniqueTreatedPatients) * 100, 1);
        } elseif ($uniqueUpsoldPatients === 0) {
            $upsellRate = 0.0;
        }
        // else: uniqueTreatedPatients=0 AND uniqueUpsoldPatients>0 → stays null (N/A)

        return [
            'upsell_revenue' => round($upsellRevenue, 2),
            'unique_upsold_patients' => $uniqueUpsoldPatients,
            'unique_treated_patients' => $uniqueTreatedPatients,
            'upsell_rate' => $upsellRate,
        ];
    }

    /**
     * Calculate upsell revenue for multiple doctors (benchmark).
     *
     * @param array $doctorIds
     * @param string $startDate
     * @param string $endDate
     * @param int $accountId
     * @return array [doctorId => upsell_revenue]
     */
    public function calculateForDoctors(array $doctorIds, string $startDate, string $endDate, int $accountId): array
    {
        if (empty($doctorIds)) {
            return [];
        }

        return DB::table('package_services as ps')
            ->join('packages as p', 'ps.package_id', '=', 'p.id')
            ->join('appointments as a', 'p.appointment_id', '=', 'a.id')
            ->whereIn('ps.sold_by', $doctorIds)
            // Exclude only self-consultation sales (type=1 where seller == consulting doctor)
            ->where(function ($q) {
                $q->where('a.appointment_type_id', '!=', AppointmentType::Consultancy->value)
                  ->orWhereColumn('a.doctor_id', '!=', 'ps.sold_by');
            })
            ->whereBetween('ps.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->select('ps.sold_by as doctor_id', DB::raw('SUM(ps.tax_including_price) as total'))
            ->groupBy('ps.sold_by')
            ->pluck('total', 'doctor_id')
            ->map(fn($v) => round((float) $v, 2))
            ->toArray();
    }

    /**
     * @return array
     */
    private function emptyResult(): array
    {
        return [
            'upsell_revenue' => 0,
            'unique_upsold_patients' => 0,
            'unique_treated_patients' => 0,
            'upsell_rate' => 0.0,
        ];
    }
}
