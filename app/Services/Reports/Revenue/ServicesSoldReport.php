<?php

declare(strict_types=1);

namespace App\Services\Reports\Revenue;

use App\Helpers\ACL;
use App\Models\AppointmentStatuses;
use App\Models\Locations;
use App\Models\Services;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ServicesSoldReport
{
    /**
     * Generate services sold report.
     *
     * Extracted from FinanceReportController::serviceSoldreport()
     * Business logic preserved exactly.
     */
    public function generate(
        ?string $startDate,
        ?string $endDate,
        ?array $locationId,
        ?string $serviceId,
    ): array {
        $accountId = Auth::user()->account_id;

        // Determine locations
        $isAllCentres = empty($locationId) || (is_array($locationId) && $locationId[0] === null);
        $resolvedLocationId = $isAllCentres ? ACL::getUserCentres() : $locationId;

        // Format dates with time for this report
        $startDateTime = $startDate ? "{$startDate} 00:00:00" : null;
        $endDateTime = $endDate ? "{$endDate} 23:59:59" : null;

        // Get arrived and converted appointment status IDs
        $arrivedStatus = AppointmentStatuses::where(['account_id' => $accountId, 'is_arrived' => 1])->first();
        $convertedStatus = AppointmentStatuses::where(['account_id' => $accountId, 'is_converted' => 1])->first();
        $arrivedStatusId = $arrivedStatus?->id ?? 2;
        $convertedStatusId = $convertedStatus?->id;
        $statusIds = $convertedStatusId ? [$arrivedStatusId, $convertedStatusId] : [$arrivedStatusId];

        // Build query
        $query = DB::table('appointments')
            ->join('invoices', 'invoices.appointment_id', '=', 'appointments.id')
            ->where('appointments.appointment_type_id', 2)
            ->whereIn('appointments.appointment_status_id', $statusIds)
            ->when(! $isAllCentres, fn ($q) => $q->whereIn('appointments.location_id', $resolvedLocationId))
            ->when($startDateTime && $endDateTime, fn ($q) => $q->whereBetween('appointments.scheduled_date', [$startDateTime, $endDateTime]))
            ->when($serviceId, fn ($q) => $q->where('appointments.service_id', $serviceId));

        // Grouping
        if ($isAllCentres) {
            $query->select(
                'appointments.service_id',
                DB::raw('COUNT(appointments.id) as total_sold')
            )->groupBy('appointments.service_id');
        } else {
            $query->select(
                'appointments.service_id',
                'appointments.location_id',
                DB::raw('COUNT(appointments.id) as total_sold')
            )->groupBy('appointments.service_id', 'appointments.location_id');
        }

        $soldServices = $query->get();

        // Summary stats
        $grouped = $isAllCentres
            ? $soldServices
            : $soldServices->groupBy('service_id')->map(fn ($group) => (object) [
                'service_id' => $group->first()->service_id,
                'total_sold' => $group->sum('total_sold'),
            ]);

        $mostSold = $grouped->sortByDesc('total_sold')->first();
        $leastSold = $grouped->sortBy('total_sold')->first();

        // Services and locations
        $serviceIds = $soldServices->pluck('service_id')->unique();
        $services = Services::whereIn('id', $serviceIds)->get()->keyBy('id');

        $locationIds = $soldServices->pluck('location_id')->filter()->unique();
        $locations = Locations::whereIn('id', $locationIds)->get()->keyBy('id');

        return [
            'soldServices' => $soldServices,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'locationId' => $resolvedLocationId,
            'serviceId' => $serviceId,
            'mostSold' => $mostSold,
            'leastSold' => $leastSold,
            'services' => $services,
            'locations' => $locations,
        ];
    }
}
