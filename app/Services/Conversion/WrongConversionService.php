<?php

declare(strict_types=1);

namespace App\Services\Conversion;

use App\Models\Appointments;
use App\Models\AppointmentStatuses;
use App\Models\Invoices;
use App\Models\Leads;
use App\Models\LeadsServices;
use App\Models\LeadStatuses;
use App\Models\Packages;
use App\Models\PackageBundles;
use App\Models\PackageService;
use App\Models\PackageAdvances;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class WrongConversionService
{
    public function getIndexData(string $date): array
    {
        $convertedStatus = AppointmentStatuses::where('is_converted', 1)->first();
        $arrivedStatus = AppointmentStatuses::where('is_arrived', 1)->first();

        if (!$convertedStatus) {
            return [
                'appointments' => collect(),
                'validIds' => [],
                'date' => $date,
                'error' => 'Converted status not found',
            ];
        }

        $allConverted = Appointments::with(['patient', 'location', 'doctor', 'service'])
            ->where('appointment_type_id', 1)
            ->where('base_appointment_status_id', $convertedStatus->id)
            ->whereDate('converted_at', $date)
            ->whereNull('deleted_at')
            ->get();

        $validIds = [];
        $invalidAppointments = [];

        foreach ($allConverted as $appointment) {
            $isValid = $this->isValidConversion($appointment);
            if ($isValid) {
                $validIds[] = $appointment->id;
            } else {
                $invalidAppointments[] = $appointment;
            }
        }

        return [
            'appointments' => collect($invalidAppointments),
            'validIds' => $validIds,
            'date' => $date,
            'totalConverted' => $allConverted->count(),
            'validCount' => count($validIds),
            'invalidCount' => count($invalidAppointments),
            'arrivedStatusId' => $arrivedStatus?->id,
        ];
    }

    private function isValidConversion(mixed $appointment): bool
    {
        $invoice = Invoices::where('appointment_id', $appointment->id)
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'asc')
            ->first();

        if (!$invoice) {
            return false;
        }

        $invoiceDate = Carbon::parse($invoice->created_at)->format('Y-m-d');

        $patientPackageIds = Packages::where('patient_id', $appointment->patient_id)
            ->whereNull('deleted_at')
            ->pluck('id');

        if ($patientPackageIds->isEmpty()) {
            return false;
        }

        $packageBundleIds = PackageBundles::whereIn('package_id', $patientPackageIds)->pluck('id');

        $serviceAfterInvoice = PackageService::whereIn('package_bundle_id', $packageBundleIds)
            ->whereDate('created_at', '>=', $invoiceDate)
            ->exists();

        if (!$serviceAfterInvoice) {
            return false;
        }

        $paymentAfterInvoice = PackageAdvances::whereIn('package_id', $patientPackageIds)
            ->where('cash_flow', 'in')
            ->where('cash_amount', '>', 0)
            ->whereNull('deleted_at')
            ->whereDate('created_at', '>=', $invoiceDate)
            ->exists();

        if (! $paymentAfterInvoice) {
            return false;
        }

        // Net-cash check (finance policy Q2=strict, 2026-05-03):
        // a conversion that has been refunded down to ≤ 0 is no
        // longer a valid conversion. Surfaces refunded "ghost"
        // conversions in /admin/wrong-conversions for the operator
        // to clean up. Defers to the SOT helper.
        if (ConversionStateService::netCashForAppointment((int) $appointment->id) <= 0) {
            return false;
        }

        return true;
    }

    public function resetAppointment(int $id): array
    {
        $arrivedStatus = AppointmentStatuses::where('is_arrived', 1)->first();
        if (!$arrivedStatus) {
            return ['success' => false, 'error' => 'Arrived status not found'];
        }

        $appointment = Appointments::find($id);
        if (!$appointment) {
            return ['success' => false, 'error' => 'Appointment not found'];
        }

        DB::transaction(function () use ($appointment, $arrivedStatus): void {
            $this->revertAppointmentRow($appointment, $arrivedStatus->id);
        });

        return ['success' => true, 'message' => "Appointment #{$id} reset to arrived status"];
    }

    public function resetAllAppointments(array $ids): array
    {
        $arrivedStatus = AppointmentStatuses::where('is_arrived', 1)->first();
        if (!$arrivedStatus) {
            return ['success' => false, 'error' => 'Arrived status not found'];
        }

        // Walk per-row so the lead/leads_services cascade and the
        // Meta-CAPI flag clear (per-appointment, per-lead) all happen
        // for every selection. Bulk update would skip the cascade.
        $appointments = Appointments::whereIn('id', $ids)->get();
        $count = 0;
        DB::transaction(function () use ($appointments, $arrivedStatus, &$count): void {
            foreach ($appointments as $appointment) {
                $this->revertAppointmentRow($appointment, $arrivedStatus->id);
                $count++;
            }
        });

        return ['success' => true, 'message' => "{$count} appointments reset to arrived status"];
    }

    /**
     * Revert one appointment from Converted back to Arrived AND undo
     * every cascade `PlanService::markAppointmentAsConvertedOptimized`
     * (and `PackageAdvances::updateLeadStatusToConverted`) wrote when
     * the conversion fired:
     *
     *   - `appointments.base/appointment_status_id` → Arrived
     *   - `appointments.converted_at`               → NULL
     *   - `appointments.meta_purchase_sent`         → 0  (so a corrected
     *     conversion can re-fire the Meta CAPI Purchase event; without
     *     this clear the per-lead idempotency guard at
     *     `PlanService.php:3399-3402` permanently silences future events
     *     for this lead)
     *   - `leads.lead_status_id`                    → Arrived (the lead
     *     was bumped Converted by `PackageAdvances::updateLeadStatusTo-
     *     Converted` AND `PlanService::updateLeadStatusToConverted`;
     *     reverting to Arrived undoes both, since the lead was already
     *     at Arrived before the payment landed)
     *   - `leads_services.lead_status_id`           → Arrived (same)
     *
     * Lead-status target lookup mirrors the ConsultancyInvoiceService
     * pattern (`is_arrived=1`, scoped to the lead's account_id).
     */
    private function revertAppointmentRow(Appointments $appointment, int $arrivedStatusId): void
    {
        // Reset all three Meta CAPI dedup flags so a corrected
        // conversion can re-fire booked / arrived / purchase events
        // for this appointment. Without these clears, the per-
        // appointment guards in createAppointment / Consultancy-
        // InvoiceService / PlanService permanently silence future
        // sends for this row.
        $appointment->update([
            'base_appointment_status_id' => $arrivedStatusId,
            'appointment_status_id'      => $arrivedStatusId,
            'converted_at'               => null,
            'meta_purchase_sent'         => 0,
            'meta_booked_sent'           => 0,
            'meta_arrived_sent'          => 0,
        ]);

        if (! $appointment->lead_id) {
            return;
        }

        $lead = Leads::find($appointment->lead_id);
        if (! $lead) {
            return;
        }

        $arrivedLeadStatus = LeadStatuses::where([
            'account_id'  => $lead->account_id,
            'is_arrived'  => 1,
        ])->first();
        if (! $arrivedLeadStatus) {
            return;
        }

        Leads::where('id', $lead->id)
            ->update(['lead_status_id' => $arrivedLeadStatus->id]);

        if ($appointment->service_id) {
            LeadsServices::where('lead_id', $lead->id)
                ->where('service_id', $appointment->service_id)
                ->update(['lead_status_id' => $arrivedLeadStatus->id]);
        }
    }
}
