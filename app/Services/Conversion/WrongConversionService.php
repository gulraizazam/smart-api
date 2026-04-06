<?php

declare(strict_types=1);

namespace App\Services\Conversion;

use App\Models\Appointments;
use App\Models\AppointmentStatuses;
use App\Models\Invoices;
use App\Models\Packages;
use App\Models\PackageBundles;
use App\Models\PackageService;
use App\Models\PackageAdvances;
use Carbon\Carbon;

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
            'arrivedStatusId' => $arrivedStatus ? $arrivedStatus->id : null,
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

        return $paymentAfterInvoice;
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

        $appointment->update([
            'base_appointment_status_id' => $arrivedStatus->id,
            'appointment_status_id' => $arrivedStatus->id,
            'converted_at' => null,
        ]);

        return ['success' => true, 'message' => "Appointment #{$id} reset to arrived status"];
    }

    public function resetAllAppointments(array $ids): array
    {
        $arrivedStatus = AppointmentStatuses::where('is_arrived', 1)->first();

        if (!$arrivedStatus) {
            return ['success' => false, 'error' => 'Arrived status not found'];
        }

        $count = Appointments::whereIn('id', $ids)->update([
            'base_appointment_status_id' => $arrivedStatus->id,
            'appointment_status_id' => $arrivedStatus->id,
            'converted_at' => null,
        ]);

        return ['success' => true, 'message' => "{$count} appointments reset to arrived status"];
    }
}
