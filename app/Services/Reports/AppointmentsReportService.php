<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Appointments;
use App\Models\AppointmentStatuses;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AppointmentsReportService
{
    /**
     * Generate the appointments report.
     *
     * Finds consultation appointments (type_id=1) with arrived/converted status
     * where the first invoice was created within the given time interval (minutes)
     * from the appointment creation.
     */
    /**
     * @param int[]|null $centreIds
     */
    public function generate(
        string $startDate,
        string $endDate,
        int $timeInterval,
        ?array $centreIds = null,
        ?int $createdBy = null,
    ): Collection {
        $statusIds = $this->getArrivedConvertedStatusIds();

        // Pre-aggregate first invoice per appointment in a derived table.
        // This replaces a per-row correlated subquery + bulk hasInvoices
        // eager-load with a single grouped index scan over invoices, joined
        // back to the narrow appointment set.
        $firstInvoice = DB::table('invoices')
            ->select('appointment_id', DB::raw('MIN(created_at) as first_invoice_at'))
            ->whereNotNull('appointment_id')
            ->groupBy('appointment_id');

        return Appointments::query()
            ->select('appointments.*')
            ->selectRaw('first_invoice.first_invoice_at as first_invoice_at')
            ->joinSub($firstInvoice, 'first_invoice', 'first_invoice.appointment_id', '=', 'appointments.id')
            ->with([
                'patient:id,name',
                'location:id,name',
                'user:id,name',
            ])
            ->where('appointments.appointment_type_id', 1)
            ->whereIn('appointments.appointment_status_id', $statusIds)
            ->whereBetween('appointments.created_at', [$startDate, $endDate])
            ->when(!empty($centreIds), fn ($q) => $q->whereIn('appointments.location_id', $centreIds))
            ->when($createdBy, fn ($q, $id) => $q->where('appointments.created_by', $id))
            ->whereRaw(
                'TIMESTAMPDIFF(MINUTE, appointments.created_at, first_invoice.first_invoice_at) <= ?',
                [$timeInterval]
            )
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
