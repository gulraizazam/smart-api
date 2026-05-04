<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\AppointmentType;
use App\Models\PackageAdvances;
use Illuminate\Support\Facades\DB;

/**
 * Doctor Incentive report — port of the legacy
 * `FinanceArrivalReportController::loadIncentiveReport` flow.
 *
 * Two views are returned depending on whether a doctor is selected:
 *
 * - Aggregate (no doctor): grand total revenue minus refunds plus a
 *   month-wise revenue breakdown (anchored on the appointment's
 *   scheduled_date so the month columns match the legacy view).
 * - Doctor-scoped: total revenue across the doctor's appointments,
 *   the conversion amount (first-payment-in-range, consultancy only),
 *   their difference, and the per-patient payment ledger that drives
 *   the legacy "Patient Payments" table.
 */
class DoctorIncentiveReportService
{
    /**
     * @param  int[]  $centreIds  Centre IDs to scope the query (already ACL-filtered)
     */
    public function generate(array $centreIds, ?int $doctorId, string $startDate, string $endDate): array
    {
        $startDt = $startDate.' 00:00:00';
        $endDt = $endDate.' 23:59:59';

        if ($doctorId !== null) {
            return $this->doctorView($centreIds, $doctorId, $startDt, $endDt);
        }

        return $this->aggregateView($centreIds, $startDt, $endDt);
    }

    /**
     * Total revenue + month-wise breakdown for all doctors in scope.
     */
    private function aggregateView(array $centreIds, string $start, string $end): array
    {
        $totalIn = (float) PackageAdvances::whereIn('package_advances.location_id', $centreIds)
            ->whereBetween('package_advances.created_at', [$start, $end])
            ->where('cash_flow', 'in')
            ->where('cash_amount', '>', 0)
            ->sum('package_advances.cash_amount');

        $totalRefund = (float) PackageAdvances::whereIn('package_advances.location_id', $centreIds)
            ->whereBetween('package_advances.created_at', [$start, $end])
            ->where('cash_flow', 'out')
            ->where('cash_amount', '>', 0)
            ->where('is_refund', 1)
            ->sum('cash_amount');

        $totalRevenue = $totalIn - $totalRefund;

        $monthly = PackageAdvances::whereIn('package_advances.location_id', $centreIds)
            ->where('cash_flow', 'in')
            ->where('cash_amount', '>', 0)
            ->where('is_refund', 0)
            ->whereBetween('package_advances.created_at', [$start, $end])
            ->join('appointments', 'package_advances.appointment_id', '=', 'appointments.id')
            ->select(
                DB::raw('DATE_FORMAT(appointments.scheduled_date, "%Y-%m") as revenue_month'),
                DB::raw('SUM(package_advances.cash_amount) as monthly_total'),
            )
            ->groupBy('revenue_month')
            ->orderBy('revenue_month')
            ->get();

        $monthRows = $monthly->map(fn ($row) => [
            'month' => $row->revenue_month,
            'monthly_total' => (float) $row->monthly_total,
        ])->values()->all();

        return [
            'mode' => 'aggregate',
            'total_revenue' => $totalRevenue,
            'total_revenue_in' => $totalIn,
            'total_refund' => $totalRefund,
            'month_wise_revenue' => $monthRows,
        ];
    }

    /**
     * Doctor-scoped view: revenue, conversion, diff, and the patient ledger.
     */
    private function doctorView(array $centreIds, int $doctorId, string $start, string $end): array
    {
        // Appointments whose first payment landed inside the window —
        // matches the legacy "what got converted in this period" definition.
        $appointmentsInRange = PackageAdvances::select(
                'appointment_id',
                DB::raw('MIN(created_at) as first_payment_date'),
            )
            ->where('cash_flow', 'in')
            ->where('cash_amount', '>', 0)
            ->where('is_refund', 0)
            ->whereIn('location_id', $centreIds)
            ->groupBy('appointment_id')
            ->havingRaw('first_payment_date BETWEEN ? AND ?', [$start, $end])
            ->pluck('appointment_id');

        // Conversion amount: payments inside the window for those
        // first-converted-in-range appointments, restricted to consultancy
        // appointments owned by the selected doctor.
        $totalCashAmount = (float) PackageAdvances::where('cash_flow', 'in')
            ->where('cash_amount', '>', 0)
            ->whereBetween('created_at', [$start, $end])
            ->whereIn('location_id', $centreIds)
            ->whereIn('appointment_id', function ($query) use ($appointmentsInRange, $doctorId) {
                $query->select('id')
                    ->from('appointments')
                    ->where('appointment_type_id', AppointmentType::Consultancy->value)
                    ->where('doctor_id', $doctorId)
                    ->whereIn('id', $appointmentsInRange);
            })
            ->sum('cash_amount');

        // Total revenue: every payment-in inside the window across all of
        // the doctor's appointments (consultancy or treatment).
        $totalDoctorRevenue = (float) PackageAdvances::where('cash_flow', 'in')
            ->where('cash_amount', '>', 0)
            ->where('is_refund', 0)
            ->whereIn('location_id', $centreIds)
            ->whereBetween('package_advances.created_at', [$start, $end])
            ->whereIn('appointment_id', function ($query) use ($doctorId) {
                $query->select('id')->from('appointments')->where('doctor_id', $doctorId);
            })
            ->sum('cash_amount');

        $diff = $totalDoctorRevenue - $totalCashAmount;

        $patients = PackageAdvances::select(
                'appointments.patient_id',
                'appointments.scheduled_date',
                'users.name as patient_name',
                'package_advances.created_at as payment_date',
                'package_advances.cash_amount',
            )
            ->join('appointments', 'appointments.id', '=', 'package_advances.appointment_id')
            ->join('users', 'users.id', '=', 'appointments.patient_id')
            ->where('package_advances.cash_flow', 'in')
            ->where('package_advances.cash_amount', '>', 0)
            ->whereIn('package_advances.location_id', $centreIds)
            ->whereBetween('package_advances.created_at', [$start, $end])
            ->where('appointments.doctor_id', $doctorId)
            ->orderBy('package_advances.created_at')
            ->get();

        return [
            'mode' => 'doctor',
            'total_doctor_revenue' => $totalDoctorRevenue,
            'total_cash_amount' => $totalCashAmount,
            'diff' => $diff,
            'patients' => $patients->map(fn ($p) => [
                'patient_id' => $p->patient_id !== null ? (int) $p->patient_id : null,
                'patient_name' => (string) ($p->patient_name ?? ''),
                'scheduled_date' => $p->scheduled_date,
                'payment_date' => $p->payment_date,
                'cash_amount' => (float) $p->cash_amount,
            ])->values()->all(),
        ];
    }
}
