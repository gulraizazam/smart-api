<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\AppointmentStatuses;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ArrivedNotConvertedService
{
    /**
     * Generate the arrived-not-converted report.
     *
     * Finds patients with arrived/converted consultation appointments
     * who have zero cash inflow (package_advances.cash_amount sum < 1).
     */
    /**
     * @param int[]|null $locationIds
     */
    public function generate(
        string $startDate,
        string $endDate,
        ?array $locationIds = null,
        ?int $doctorId = null,
        ?int $serviceId = null,
    ): Collection {
        $statusIds = $this->getArrivedConvertedStatusIds();

        // Subquery: pick the latest qualifying appointment per patient.
        // Using MAX(id) gives a deterministic single appointment per user, which is
        // ONLY_FULL_GROUP_BY-safe (vs. the previous arbitrary pick from a non-strict GROUP BY).
        $latestAppointmentPerPatient = DB::table('appointments')
            ->select('patient_id', DB::raw('MAX(id) as latest_apt_id'))
            ->where('appointment_type_id', 1)
            ->whereIn('appointment_status_id', $statusIds)
            ->whereBetween('scheduled_date', [$startDate, $endDate])
            ->when(!empty($locationIds), fn ($q) => $q->whereIn('location_id', $locationIds))
            ->when($doctorId, fn ($q, $id) => $q->where('doctor_id', $id))
            ->when($serviceId, fn ($q, $id) => $q->where('service_id', $id))
            ->groupBy('patient_id');

        // Subquery: total incoming cash per patient. The previous query joined
        // package_advances directly into the GROUP BY users.id, which inflated SUMs
        // via the cartesian product with appointments — but the only filter was `< 1`,
        // so the inflation never affected the boolean result. This subquery computes
        // the true per-patient sum without that side-effect.
        $cashInPerPatient = DB::table('package_advances')
            ->select('patient_id', DB::raw('SUM(cash_amount) as total_cash'))
            ->where('cash_flow', 'in')
            ->groupBy('patient_id');

        return DB::table('users')
            ->select(
                'users.id',
                'users.name',
                'users.phone',
                'appointments.doctor_id',
                'appointments.location_id',
                'appointments.service_id',
                'appointments.scheduled_date',
                'appointments.id as apt_id',
                'doctors.name as doctor_name',
                'services.name as service_name',
                'locations.name as location_name',
            )
            ->joinSub($latestAppointmentPerPatient, 'latest_apt', 'latest_apt.patient_id', '=', 'users.id')
            ->join('appointments', 'appointments.id', '=', 'latest_apt.latest_apt_id')
            ->leftJoinSub($cashInPerPatient, 'cash_in', 'cash_in.patient_id', '=', 'users.id')
            ->leftJoin('users as doctors', 'doctors.id', '=', 'appointments.doctor_id')
            ->leftJoin('services', 'services.id', '=', 'appointments.service_id')
            ->leftJoin('locations', 'locations.id', '=', 'appointments.location_id')
            ->whereRaw('COALESCE(cash_in.total_cash, 0) < 1')
            ->orderBy('appointments.scheduled_date', 'desc')
            ->get();
    }

    /**
     * Get the appointment status IDs for arrived and converted statuses.
     *
     * @return array<int>
     */
    private function getArrivedConvertedStatusIds(): array
    {
        $accountId = Auth::user()->account_id;

        $arrivedStatus = AppointmentStatuses::where([
            'account_id' => $accountId,
            'is_arrived' => 1,
        ])->first();

        $convertedStatus = AppointmentStatuses::where([
            'account_id' => $accountId,
            'is_converted' => 1,
        ])->first();

        $arrivedStatusId = $arrivedStatus?->id ?? 2;

        return $convertedStatus
            ? [$arrivedStatusId, $convertedStatus->id]
            : [$arrivedStatusId];
    }
}
