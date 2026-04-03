<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Appointments;
use App\Models\AppointmentStatuses;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class AppointmentsReportService
{
    /**
     * Generate the appointments report.
     *
     * Finds consultation appointments (type_id=1) with arrived/converted status
     * where the first invoice was created within the given time interval (minutes)
     * from the appointment creation.
     */
    public function generate(
        string $startDate,
        string $endDate,
        int $timeInterval,
        ?int $centreId = null,
        ?int $createdBy = null,
    ): Collection {
        $statusIds = $this->getArrivedConvertedStatusIds();

        return Appointments::with([
            'patient:id,name',
            'location:id,name',
            'user:id,name',
            'hasInvoices' => fn ($q) => $q->orderBy('created_at', 'asc'),
        ])
            ->where('appointment_type_id', 1)
            ->whereIn('appointment_status_id', $statusIds)
            ->whereHas('hasInvoices', function ($query) use ($timeInterval) {
                $query->havingRaw(
                    'TIMESTAMPDIFF(MINUTE, appointments.created_at, MIN(invoices.created_at)) <= ?',
                    [$timeInterval]
                );
            })
            ->when($centreId, fn ($q, $id) => $q->where('location_id', $id))
            ->when($createdBy, fn ($q, $id) => $q->where('created_by', $id))
            ->whereBetween('created_at', [$startDate, $endDate])
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
