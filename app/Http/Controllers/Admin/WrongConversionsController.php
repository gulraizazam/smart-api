<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointments;
use App\Models\AppointmentStatuses;
use App\Models\Invoices;
use App\Models\Packages;
use App\Models\PackageBundles;
use App\Models\PackageService;
use App\Models\PackageAdvances;
use Carbon\Carbon;
use Illuminate\Http\Request;

class WrongConversionsController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->get('date', Carbon::yesterday()->format('Y-m-d'));
        
        // Get converted status
        $convertedStatus = AppointmentStatuses::where('is_converted', 1)->first();
        $arrivedStatus = AppointmentStatuses::where('is_arrived', 1)->first();
        
        if (!$convertedStatus) {
            return view('admin.wrong_conversions', [
                'appointments' => collect(),
                'validIds' => [],
                'date' => $date,
                'error' => 'Converted status not found'
            ]);
        }
        
        // Get all converted appointments for the date
        $allConverted = Appointments::with(['patient', 'location', 'doctor', 'service'])
            ->where('appointment_type_id', 1)
            ->where('base_appointment_status_id', $convertedStatus->id)
            ->whereDate('converted_at', $date)
            ->whereNull('deleted_at')
            ->get();
        
        // Find valid conversions
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
        
        return view('admin.wrong_conversions', [
            'appointments' => collect($invalidAppointments),
            'validIds' => $validIds,
            'date' => $date,
            'totalConverted' => $allConverted->count(),
            'validCount' => count($validIds),
            'invalidCount' => count($invalidAppointments),
            'arrivedStatusId' => $arrivedStatus ? $arrivedStatus->id : null
        ]);
    }
    
    private function isValidConversion($appointment)
    {
        // Step 1: Get invoice for this appointment
        $invoice = Invoices::where('appointment_id', $appointment->id)
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'asc')
            ->first();
        
        if (!$invoice) {
            return false;
        }
        
        $invoiceDate = Carbon::parse($invoice->created_at)->format('Y-m-d');
        
        // Step 2: Get all packages for this patient
        $patientPackageIds = Packages::where('patient_id', $appointment->patient_id)
            ->whereNull('deleted_at')
            ->pluck('id');
        
        if ($patientPackageIds->isEmpty()) {
            return false;
        }
        
        // Step 3: Check if service added on/after invoice date
        $packageBundleIds = PackageBundles::whereIn('package_id', $patientPackageIds)->pluck('id');
        
        $serviceAfterInvoice = PackageService::whereIn('package_bundle_id', $packageBundleIds)
            ->whereDate('created_at', '>=', $invoiceDate)
            ->exists();
        
        if (!$serviceAfterInvoice) {
            return false;
        }
        
        // Step 4: Check if payment added on/after invoice date
        $paymentAfterInvoice = PackageAdvances::whereIn('package_id', $patientPackageIds)
            ->where('cash_flow', 'in')
            ->where('cash_amount', '>', 0)
            ->whereNull('deleted_at')
            ->whereDate('created_at', '>=', $invoiceDate)
            ->exists();
        
        return $paymentAfterInvoice;
    }
    
    public function reset(Request $request, $id)
    {
        $arrivedStatus = AppointmentStatuses::where('is_arrived', 1)->first();
        
        if (!$arrivedStatus) {
            return back()->with('error', 'Arrived status not found');
        }
        
        $appointment = Appointments::find($id);
        if (!$appointment) {
            return back()->with('error', 'Appointment not found');
        }
        
        $appointment->update([
            'base_appointment_status_id' => $arrivedStatus->id,
            'appointment_status_id' => $arrivedStatus->id,
            'converted_at' => null
        ]);
        
        return back()->with('success', "Appointment #{$id} reset to arrived status");
    }
    
    public function resetAll(Request $request)
    {
        $ids = $request->get('ids', []);
        $arrivedStatus = AppointmentStatuses::where('is_arrived', 1)->first();
        
        if (!$arrivedStatus) {
            return back()->with('error', 'Arrived status not found');
        }
        
        $count = Appointments::whereIn('id', $ids)->update([
            'base_appointment_status_id' => $arrivedStatus->id,
            'appointment_status_id' => $arrivedStatus->id,
            'converted_at' => null
        ]);
        
        return back()->with('success', "{$count} appointments reset to arrived status");
    }
}
