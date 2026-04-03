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
    public function generate(
        string $startDate,
        string $endDate,
        ?int $locationId = null,
        ?int $doctorId = null,
        ?int $serviceId = null,
    ): Collection {
        $statusIds = $this->getArrivedConvertedStatusIds();

        return DB::table('users')
            ->select(
                DB::raw('SUM(package_advances.cash_amount) as cash_amount_test'),
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
            ->join('appointments', 'appointments.patient_id', '=', 'users.id')
            ->leftJoin('package_advances', function ($join) {
                $join->on('package_advances.patient_id', '=', 'users.id')
                    ->where('package_advances.cash_flow', '=', 'in');
            })
            ->leftJoin('users as doctors', 'doctors.id', '=', 'appointments.doctor_id')
            ->leftJoin('services', 'services.id', '=', 'appointments.service_id')
            ->leftJoin('locations', 'locations.id', '=', 'appointments.location_id')
            ->where('appointments.appointment_type_id', 1)
            ->whereIn('appointments.appointment_status_id', $statusIds)
            ->whereBetween('appointments.scheduled_date', [$startDate, $endDate])
            ->when($locationId, fn ($q, $id) => $q->where('appointments.location_id', $id))
            ->when($doctorId, fn ($q, $id) => $q->where('appointments.doctor_id', $id))
            ->when($serviceId, fn ($q, $id) => $q->where('appointments.service_id', $id))
            ->groupBy('users.id')
            ->havingRaw('cash_amount_test < 1')
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
