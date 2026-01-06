<?php

namespace App\Helpers;

use App\Models\Activity;
use Auth;

class ActivityLogger
{
    /**
     * Log a lead created activity
     * Format: XYZ created a SERVICE_NAME lead for PATIENT_NAME in LOCATION_NAME
     */
    public static function logLeadCreated($lead, $location = null, $service = null)
    {
        $locationName = '';
        if ($location) {
            $locationName = ($location->city->name ?? '') . '-' . ($location->name ?? '');
        }
        
        $serviceName = $service->name ?? '';
        $creatorName = Auth::user()->name ?? 'System';
        $patientName = $lead->name ?? 'Unknown';
        
        // Format: XYZ created a SERVICE_NAME lead for PATIENT_NAME in LOCATION_NAME
        $description = '<span class="highlight">' . $creatorName . '</span> created a <span class="highlight-orange">' . ($serviceName ?: 'Service') . '</span> lead for <span class="highlight-orange">' . $patientName . '</span>' . ($locationName ? ' in <span class="highlight">' . $locationName . '</span>' : '');
        
        return Activity::create([
            'account_id' => Auth::user()->account_id ?? $lead->account_id,
            'action' => 'Lead Created',
            'activity_type' => 'lead_created',
            'description' => $description,
            'patient' => $patientName,
            'patient_id' => $lead->patient_id ?? null,
            'lead_id' => $lead->id,
            'lead_status' => 'Open',
            'lead_status_id' => $lead->lead_status_id,
            'service' => $serviceName,
            'service_id' => $service->id ?? null,
            'location' => $locationName,
            'centre_id' => $location->id ?? $lead->location_id ?? null,
            'created_by' => Auth::user()->id ?? $lead->created_by,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Log a lead status change to Booked (consultation created)
     * Format: SERVICE_NAME lead status changed to LEAD_STATUS_NAME against PATIENT_NAME in LOCATION_NAME
     */
    public static function logLeadBooked($lead, $appointment = null, $location = null, $service = null)
    {
        $locationName = '';
        if ($location) {
            $locationName = ($location->city->name ?? '') . '-' . ($location->name ?? '');
        }
        
        $serviceName = $service->name ?? '';
        $patientName = $lead->name ?? ($appointment->patient->name ?? 'Unknown');
        
        // Format: SERVICE_NAME lead status changed to LEAD_STATUS_NAME against PATIENT_NAME in LOCATION_NAME
        $description = '<span class="highlight-orange">' . ($serviceName ?: 'Service') . '</span> lead status changed to <span class="highlight-green">Booked</span> against <span class="highlight-orange">' . $patientName . '</span>' . ($locationName ? ' in <span class="highlight">' . $locationName . '</span>' : '');
        
        return Activity::create([
            'account_id' => Auth::user()->account_id ?? $lead->account_id,
            'action' => 'Lead Booked',
            'activity_type' => 'lead_booked',
            'description' => $description,
            'patient' => $patientName,
            'patient_id' => $lead->patient_id ?? ($appointment->patient_id ?? null),
            'lead_id' => $lead->id,
            'lead_status' => 'Booked',
            'lead_status_id' => $lead->lead_status_id,
            'appointment_id' => $appointment->id ?? null,
            'appointment_type' => 'Consultation',
            'service' => $serviceName,
            'service_id' => $service->id ?? ($appointment->service_id ?? null),
            'location' => $locationName,
            'centre_id' => $location->id ?? ($appointment->location_id ?? $lead->location_id ?? null),
            'schedule_date' => $appointment->scheduled_date ?? null,
            'created_by' => Auth::user()->id ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Log a lead status change to Arrived (invoice created / consultation completed)
     * Format: SERVICE_NAME lead status changed to LEAD_STATUS_NAME for PATIENT_NAME in LOCATION_NAME
     */
    public static function logLeadArrived($lead, $appointment = null, $invoice = null, $location = null, $service = null)
    {
        $locationName = '';
        if ($location) {
            $locationName = ($location->city->name ?? '') . '-' . ($location->name ?? '');
        }
        
        $serviceName = $service->name ?? '';
        $patientName = $lead->name ?? ($appointment->patient->name ?? 'Unknown');
        $amount = $invoice->total_price ?? 0;
        
        // Format: SERVICE_NAME lead status changed to LEAD_STATUS_NAME for PATIENT_NAME in LOCATION_NAME
        $description = '<span class="highlight-orange">' . ($serviceName ?: 'Service') . '</span> lead status changed to <span class="highlight-green">Arrived</span> for <span class="highlight-orange">' . $patientName . '</span>' . ($locationName ? ' in <span class="highlight">' . $locationName . '</span>' : '');
        
        return Activity::create([
            'account_id' => Auth::user()->account_id ?? $lead->account_id,
            'action' => 'Lead Arrived',
            'activity_type' => 'lead_arrived',
            'description' => $description,
            'patient' => $patientName,
            'patient_id' => $lead->patient_id ?? ($appointment->patient_id ?? null),
            'lead_id' => $lead->id,
            'lead_status' => 'Arrived',
            'lead_status_id' => $lead->lead_status_id,
            'appointment_id' => $appointment->id ?? null,
            'invoice_id' => $invoice->id ?? null,
            'amount' => $amount > 0 ? number_format($amount) : null,
            'appointment_type' => 'Consultation',
            'service' => $serviceName,
            'service_id' => $service->id ?? ($appointment->service_id ?? null),
            'location' => $locationName,
            'centre_id' => $location->id ?? ($appointment->location_id ?? $lead->location_id ?? null),
            'created_by' => Auth::user()->id ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Log consultation booked activity
     */
    public static function logConsultationBooked($appointment, $patient, $location = null, $service = null)
    {
        $locationName = '';
        if ($location) {
            $locationName = ($location->city->name ?? '') . '-' . ($location->name ?? '');
        }
        
        $serviceName = $service->name ?? '';
        
        return Activity::create([
            'account_id' => Auth::user()->account_id ?? $appointment->account_id,
            'action' => 'Consultation Booked',
            'activity_type' => 'consultation_booked',
            'description' => 'Consultation Booked for ' . ($patient->name ?? 'Unknown') . ($serviceName ? ' - ' . $serviceName : '') . ($locationName ? ' at ' . $locationName : ''),
            'patient' => $patient->name ?? '',
            'patient_id' => $patient->id ?? null,
            'lead_id' => $appointment->lead_id ?? null,
            'appointment_id' => $appointment->id,
            'appointment_type' => 'Consultation',
            'service' => $serviceName,
            'service_id' => $service->id ?? $appointment->service_id,
            'location' => $locationName,
            'centre_id' => $location->id ?? $appointment->location_id,
            'schedule_date' => $appointment->scheduled_date,
            'created_by' => Auth::user()->id ?? $appointment->created_by,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Log treatment booked activity
     */
    public static function logTreatmentBooked($appointment, $patient, $location = null, $service = null)
    {
        $locationName = '';
        if ($location) {
            $locationName = ($location->city->name ?? '') . '-' . ($location->name ?? '');
        }
        
        $serviceName = $service->name ?? '';
        $patientName = $patient->name ?? 'Unknown';
        $creatorName = \Auth::user()->name ?? 'System';
        $scheduleDate = $appointment->scheduled_date ? date('M j, Y', strtotime($appointment->scheduled_date)) : '';
        
        // Format: XYZ booked SERVICE_NAME Treatment for PATIENT_NAME in LOCATION_NAME on DATE
        $description = '<span class="highlight">' . $creatorName . '</span> booked <span class="highlight-orange">' . ($serviceName ?: 'Service') . '</span> Treatment for <span class="highlight-orange">' . $patientName . '</span>' . ($locationName ? ' in <span class="highlight">' . $locationName . '</span>' : '') . ($scheduleDate ? ' on <span class="highlight-purple">' . $scheduleDate . '</span>' : '');
        
        return Activity::create([
            'account_id' => Auth::user()->account_id ?? $appointment->account_id,
            'action' => 'Treatment Booked',
            'activity_type' => 'treatment_booked',
            'description' => $description,
            'patient' => $patientName,
            'patient_id' => $patient->id ?? null,
            'lead_id' => $appointment->lead_id ?? null,
            'appointment_id' => $appointment->id,
            'appointment_type' => 'Treatment',
            'service' => $serviceName,
            'service_id' => $service->id ?? $appointment->service_id,
            'location' => $locationName,
            'centre_id' => $location->id ?? $appointment->location_id,
            'schedule_date' => $appointment->scheduled_date,
            'created_by' => Auth::user()->id ?? $appointment->created_by,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Log package created activity
     */
    public static function logPackageCreated($package, $patient, $location = null)
    {
        $locationName = '';
        if ($location) {
            $locationName = ($location->city->name ?? '') . '-' . ($location->name ?? '');
        }
        
        return Activity::create([
            'account_id' => Auth::user()->account_id ?? $package->account_id,
            'action' => 'Package Created',
            'activity_type' => 'package_created',
            'description' => 'Package Created - Plan Id: ' . $package->id . ' (' . ($package->name ?? 'Package') . ') for Rs. ' . number_format($package->total_price) . ($locationName ? ' at ' . $locationName : ''),
            'patient' => $patient->name ?? '',
            'patient_id' => $patient->id ?? $package->patient_id,
            'planId' => $package->id,
            'plan_id' => $package->id,
            'package_id' => $package->id,
            'amount' => number_format($package->total_price),
            'location' => $locationName,
            'centre_id' => $location->id ?? $package->location_id,
            'created_by' => Auth::user()->id ?? $package->created_by,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Log payment received activity
     */
    public static function logPaymentReceived($payment, $package, $patient, $location = null)
    {
        $locationName = '';
        if ($location) {
            $locationName = ($location->city->name ?? '') . '-' . ($location->name ?? '');
        }
        
        $patientId = $patient->id ?? $package->patient_id ?? null;
        $createdById = Auth::check() ? Auth::user()->id : null;
        $creatorName = Auth::check() ? Auth::user()->name : 'System';
        $patientName = $patient->name ?? 'Unknown';
        
        // Format description with highlights
        $description = '<span class="highlight">' . $creatorName . '</span> received <span class="highlight-green">Rs. ' . number_format($payment->cash_amount) . '</span> from <span class="highlight-orange">' . $patientName . '</span> for <span class="highlight-orange">Plan Id: ' . $package->id . '</span>' . ($locationName ? ' in <span class="highlight">' . $locationName . '</span>' : '');
        
        return Activity::create([
            'account_id' => Auth::user()->account_id ?? $package->account_id,
            'action' => 'Payment Received',
            'activity_type' => 'payment_received',
            'description' => $description,
            'patient' => $patientName,
            'patient_id' => $patientId,
            'planId' => $package->id,
            'plan_id' => $package->id,
            'package_id' => $package->id,
            'amount' => number_format($payment->cash_amount),
            'location' => $locationName,
            'centre_id' => $location->id ?? $package->location_id,
            'created_by' => $createdById,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
    
    /**
     * Log payment updated activity
     */
    public static function logPaymentUpdated($oldAmount, $newAmount, $oldDate, $newDate, $amountChanged, $dateChanged, $package, $patient, $location = null)
    {
        $locationName = '';
        if ($location) {
            $locationName = ($location->city->name ?? '') . '-' . ($location->name ?? '');
        }
        
        $patientId = $patient->id ?? $package->patient_id ?? null;
        $createdById = Auth::check() ? Auth::user()->id : null;
        $creatorName = Auth::check() ? Auth::user()->name : 'System';
        $patientName = $patient->name ?? 'Unknown';
        
        // Format dates for display
        $oldDateFormatted = $oldDate ? date('M j, Y', strtotime($oldDate)) : '';
        $newDateFormatted = $newDate ? date('M j, Y', strtotime($newDate)) : '';
        
        // Build description based on what changed
        $description = '<span class="highlight">' . $creatorName . '</span>';
        
        if ($amountChanged && $dateChanged) {
            // Both amount and date changed
            $description .= ' changed payment amount from <span class="highlight-purple">Rs. ' . number_format($oldAmount) . '</span> to <span class="highlight-green">Rs. ' . number_format($newAmount) . '</span> and payment received date from <span class="highlight-purple">' . $oldDateFormatted . '</span> to <span class="highlight-green">' . $newDateFormatted . '</span>';
        } elseif ($dateChanged) {
            // Only date changed
            $description .= ' changed payment received date from <span class="highlight-purple">' . $oldDateFormatted . '</span> to <span class="highlight-green">' . $newDateFormatted . '</span> for <span class="highlight-green">Rs. ' . number_format($newAmount) . '</span>';
        } else {
            // Only amount changed
            $description .= ' updated payment from <span class="highlight-purple">Rs. ' . number_format($oldAmount) . '</span> to <span class="highlight-green">Rs. ' . number_format($newAmount) . '</span>';
        }
        
        $description .= ' from <span class="highlight-orange">' . $patientName . '</span> for <span class="highlight-orange">Plan Id: ' . $package->id . '</span>' . ($locationName ? ' in <span class="highlight">' . $locationName . '</span>' : '');
        
        return Activity::create([
            'account_id' => Auth::user()->account_id ?? $package->account_id,
            'action' => 'Payment Updated',
            'activity_type' => 'payment_updated',
            'description' => $description,
            'patient' => $patientName,
            'patient_id' => $patientId,
            'planId' => $package->id,
            'plan_id' => $package->id,
            'package_id' => $package->id,
            'amount' => number_format($newAmount),
            'location' => $locationName,
            'centre_id' => $location->id ?? $package->location_id,
            'created_by' => $createdById,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Log payment deleted activity
     */
    public static function logPaymentDeleted($amount, $package, $patient, $location = null)
    {
        $locationName = '';
        if ($location) {
            $locationName = ($location->city->name ?? '') . '-' . ($location->name ?? '');
        }
        
        $patientId = $patient->id ?? $package->patient_id ?? null;
        $createdById = Auth::check() ? Auth::user()->id : null;
        $creatorName = Auth::check() ? Auth::user()->name : 'System';
        $patientName = $patient->name ?? 'Unknown';
        
        // Format description with highlights (no date - timestamp shows when it happened)
        $description = '<span class="highlight">' . $creatorName . '</span> deleted <span class="highlight-green">Rs. ' . number_format($amount) . '</span> payment from <span class="highlight-orange">Plan Id: ' . $package->id . '</span> against <span class="highlight-orange">' . $patientName . '</span>' . ($locationName ? ' in <span class="highlight">' . $locationName . '</span>' : '');
        
        return Activity::create([
            'account_id' => Auth::user()->account_id ?? $package->account_id,
            'action' => 'Payment Deleted',
            'activity_type' => 'payment_deleted',
            'description' => $description,
            'patient' => $patientName,
            'patient_id' => $patientId,
            'planId' => $package->id,
            'plan_id' => $package->id,
            'package_id' => $package->id,
            'amount' => number_format($amount),
            'location' => $locationName,
            'centre_id' => $location->id ?? $package->location_id,
            'created_by' => $createdById,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Log refund made activity
     */
    public static function logRefundMade($refund, $package, $patient, $location = null)
    {
        $locationName = '';
        if ($location) {
            $locationName = ($location->city->name ?? '') . '-' . ($location->name ?? '');
        }
        
        return Activity::create([
            'account_id' => Auth::user()->account_id ?? $package->account_id,
            'action' => 'Refund Made',
            'activity_type' => 'refund_made',
            'description' => 'Refund Made Rs. ' . number_format($refund->cash_amount) . ' to ' . ($patient->name ?? 'Unknown') . ' for Plan Id: ' . $package->id . ($locationName ? ' at ' . $locationName : ''),
            'patient' => $patient->name ?? '',
            'patient_id' => $patient->id ?? $package->patient_id,
            'planId' => $package->id,
            'plan_id' => $package->id,
            'package_id' => $package->id,
            'amount' => number_format($refund->cash_amount),
            'location' => $locationName,
            'centre_id' => $location->id ?? $package->location_id,
            'created_by' => Auth::user()->id ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Log appointment rescheduled activity
     */
    public static function logAppointmentRescheduled($appointment, $patient, $oldDate, $oldTime, $newDate, $newTime, $location = null, $service = null)
    {
        $locationName = '';
        if ($location) {
            $locationName = ($location->city->name ?? '') . '-' . ($location->name ?? '');
        }
        
        $serviceName = $service->name ?? '';
        $patientName = $patient->name ?? 'Unknown';
        $creatorName = \Auth::user()->name ?? 'System';
        
        // Determine appointment type
        $appointmentType = $appointment->appointment_type_id == 1 ? 'Consultation' : 'Treatment';
        
        // Format old and new datetime
        $oldDateTime = date('M j, Y', strtotime($oldDate)) . ' ' . date('h:i A', strtotime($oldTime));
        $newDateTime = date('M j, Y', strtotime($newDate)) . ' ' . date('h:i A', strtotime($newTime));
        
        // Format: XYZ rescheduled SERVICE_NAME APPOINTMENT_TYPE for PATIENT_NAME from OLD_DATETIME to NEW_DATETIME in LOCATION_NAME
        $description = '<span class="highlight">' . $creatorName . '</span> rescheduled <span class="highlight-orange">' . ($serviceName ?: 'Service') . '</span> ' . $appointmentType . ' for <span class="highlight-orange">' . $patientName . '</span> from <span class="highlight-purple">' . $oldDateTime . '</span> to <span class="highlight-green">' . $newDateTime . '</span>' . ($locationName ? ' in <span class="highlight">' . $locationName . '</span>' : '');
        
        return Activity::create([
            'account_id' => \Auth::user()->account_id ?? null,
            'action' => 'rescheduled',
            'activity_type' => 'appointment_rescheduled',
            'description' => $description,
            'patient' => $patientName,
            'patient_id' => $patient->id ?? $appointment->patient_id,
            'appointment_id' => $appointment->id,
            'appointment_type' => $appointmentType,
            'service' => $serviceName,
            'service_id' => $service->id ?? $appointment->service_id,
            'location' => $locationName,
            'centre_id' => $location->id ?? $appointment->location_id,
            'schedule_date' => $newDate,
            'created_by' => \Auth::user()->id ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Log appointment status change activity
     */
    public static function logAppointmentStatusChange($appointment, $patient, $oldStatus, $newStatus, $location = null, $service = null)
    {
        $locationName = '';
        if ($location) {
            $locationName = ($location->city->name ?? '') . '-' . ($location->name ?? '');
        }
        
        $serviceName = $service->name ?? '';
        $patientName = $patient->name ?? 'Unknown';
        $oldStatusName = $oldStatus->name ?? 'Unknown';
        $newStatusName = $newStatus->name ?? 'Unknown';
        $creatorName = \Auth::user()->name ?? 'System';
        
        // Determine appointment type
        $appointmentType = $appointment->appointment_type_id == 1 ? 'Consultation' : 'Treatment';
        
        // Format: XYZ changed SERVICE_NAME APPOINTMENT_TYPE status from OLD_STATUS to NEW_STATUS for PATIENT_NAME in LOCATION_NAME
        $description = '<span class="highlight">' . $creatorName . '</span> changed <span class="highlight-orange">' . ($serviceName ?: 'Service') . '</span> ' . $appointmentType . ' status from <span class="highlight-purple">' . $oldStatusName . '</span> to <span class="highlight-green">' . $newStatusName . '</span> for <span class="highlight-orange">' . $patientName . '</span>' . ($locationName ? ' in <span class="highlight">' . $locationName . '</span>' : '');
        
        return Activity::create([
            'account_id' => \Auth::user()->account_id ?? null,
            'action' => 'Status Changed',
            'activity_type' => 'appointment_status_changed',
            'description' => $description,
            'patient' => $patientName,
            'patient_id' => $patient->id ?? $appointment->patient_id,
            'appointment_id' => $appointment->id,
            'appointment_type' => $appointmentType,
            'service' => $serviceName,
            'service_id' => $service->id ?? $appointment->service_id,
            'location' => $locationName,
            'centre_id' => $location->id ?? $appointment->location_id,
            'created_by' => \Auth::user()->id ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Log appointment updated activity (for field changes like doctor, service, location, patient info)
     */
    public static function logAppointmentUpdated($appointment, $patient, $changes, $location = null, $service = null)
    {
        if (empty($changes)) {
            return null;
        }
        
        $locationName = '';
        if ($location) {
            $locationName = ($location->city->name ?? '') . '-' . ($location->name ?? '');
        }
        
        $serviceName = $service->name ?? '';
        $patientName = $patient->name ?? 'Unknown';
        $creatorName = \Auth::user()->name ?? 'System';
        
        // Determine appointment type
        $appointmentType = $appointment->appointment_type_id == 1 ? 'Consultation' : 'Treatment';
        
        // Build changes description - shorter format
        $changeDescriptions = [];
        foreach ($changes as $field => $change) {
            $changeDescriptions[] = $field . ': <span class="highlight-purple">' . $change['old'] . '</span> → <span class="highlight-green">' . $change['new'] . '</span>';
        }
        
        $changesText = implode(', ', $changeDescriptions);
        
        // Format: XYZ updated PATIENT_NAME's SERVICE APPOINTMENT_TYPE - CHANGES in LOCATION
        $description = '<span class="highlight">' . $creatorName . '</span> updated <span class="highlight-orange">' . $patientName . '</span>\'s <span class="highlight-orange">' . ($serviceName ?: 'Service') . '</span> ' . $appointmentType . ' - ' . $changesText . ($locationName ? ' in <span class="highlight">' . $locationName . '</span>' : '');
        
        return Activity::create([
            'account_id' => \Auth::user()->account_id ?? null,
            'action' => 'Appointment Updated',
            'activity_type' => 'appointment_updated',
            'description' => $description,
            'patient' => $patientName,
            'patient_id' => $patient->id ?? $appointment->patient_id,
            'appointment_id' => $appointment->id,
            'appointment_type' => $appointmentType,
            'service' => $serviceName,
            'service_id' => $service->id ?? $appointment->service_id,
            'location' => $locationName,
            'centre_id' => $location->id ?? $appointment->location_id,
            'created_by' => \Auth::user()->id ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Log invoice created activity
     */
    public static function logInvoiceCreated($invoice, $appointment, $patient, $location = null, $service = null)
    {
        $locationName = '';
        if ($location) {
            $locationName = ($location->city->name ?? '') . '-' . ($location->name ?? '');
        }
        
        $serviceName = $service->name ?? '';
        
        return Activity::create([
            'account_id' => Auth::user()->account_id ?? $invoice->account_id,
            'action' => 'Invoice Created',
            'activity_type' => 'invoice_created',
            'description' => 'Invoice Created Rs. ' . number_format($invoice->total_price) . ' for ' . ($serviceName ?: 'Consultation') . ($locationName ? ' at ' . $locationName : ''),
            'patient' => $patient->name ?? '',
            'patient_id' => $patient->id ?? $invoice->patient_id,
            'appointment_id' => $appointment->id ?? $invoice->appointment_id,
            'invoice_id' => $invoice->id,
            'amount' => number_format($invoice->total_price),
            'service' => $serviceName,
            'service_id' => $service->id ?? ($appointment->service_id ?? null),
            'location' => $locationName,
            'centre_id' => $location->id ?? $invoice->location_id,
            'created_by' => Auth::user()->id ?? $invoice->created_by,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Log membership assigned activity
     */
    public static function logMembershipAssigned($patient, $membership, $membershipType = null)
    {
        $patientName = $patient->name ?? 'Unknown';
        $creatorName = Auth::check() ? Auth::user()->name : 'System';
        $membershipCode = $membership->code ?? '';
        $membershipTypeName = $membershipType->name ?? ($membership->membershipType->name ?? 'Membership');
        
        $description = '<span class="highlight">' . $creatorName . '</span> assigned <span class="highlight-green">' . $membershipTypeName . '</span> membership (Code: <span class="highlight-orange">' . $membershipCode . '</span>) to <span class="highlight-orange">' . $patientName . '</span>';
        
        return Activity::create([
            'account_id' => Auth::user()->account_id ?? null,
            'action' => 'Membership Assigned',
            'activity_type' => 'membership_assigned',
            'description' => $description,
            'patient' => $patientName,
            'patient_id' => $patient->id,
            'created_by' => Auth::user()->id ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Log membership cancelled activity
     */
    public static function logMembershipCancelled($patient, $membership, $membershipType = null)
    {
        $patientName = $patient->name ?? 'Unknown';
        $creatorName = Auth::check() ? Auth::user()->name : 'System';
        $membershipCode = $membership->code ?? '';
        $membershipTypeName = $membershipType->name ?? ($membership->membershipType->name ?? 'Membership');
        
        $description = '<span class="highlight">' . $creatorName . '</span> cancelled <span class="highlight-purple">' . $membershipTypeName . '</span> membership (Code: <span class="highlight-orange">' . $membershipCode . '</span>) for <span class="highlight-orange">' . $patientName . '</span>';
        
        return Activity::create([
            'account_id' => Auth::user()->account_id ?? null,
            'action' => 'Membership Cancelled',
            'activity_type' => 'membership_cancelled',
            'description' => $description,
            'patient' => $patientName,
            'patient_id' => $patient->id,
            'created_by' => Auth::user()->id ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Log appointment converted activity
     */
    public static function logAppointmentConverted($appointment, $patient, $location = null, $service = null, $paymentAmount = null, $planId = null)
    {
        $patientName = $patient->name ?? 'Unknown';
        $serviceName = $service->name ?? '';
        $locationName = '';
        if ($location) {
            $locationName = ($location->city->name ?? '') . '-' . ($location->name ?? '');
        }
        
        // Format: Patient's Service Consultation status changed to converted with the payment of Rs X in PlanID
        $description = '<span class="highlight-orange">' . $patientName . '</span>\'s <span class="highlight-orange">' . ($serviceName ?: 'Service') . '</span> Consultation status changed to <span class="highlight-green">Converted</span>';
        
        if ($paymentAmount) {
            $description .= ' with the payment of <span class="highlight-green">Rs ' . number_format($paymentAmount) . '</span>';
        }
        
        if ($planId) {
            $description .= ' in <span class="highlight-purple">Plan #' . sprintf('%05d', $planId) . '</span>';
        }
        
        return Activity::create([
            'account_id' => Auth::user()->account_id ?? $appointment->account_id,
            'action' => 'Appointment Converted',
            'activity_type' => 'appointment_converted',
            'description' => $description,
            'patient' => $patientName,
            'patient_id' => $patient->id ?? null,
            'appointment_id' => $appointment->id,
            'appointment_type' => 'Consultation',
            'service' => $serviceName,
            'service_id' => $service->id ?? $appointment->service_id,
            'location' => $locationName,
            'centre_id' => $location->id ?? $appointment->location_id,
            'amount' => $paymentAmount,
            'plan_id' => $planId,
            'created_by' => Auth::user()->id ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Log lead converted activity
     */
    public static function logLeadConverted($lead, $appointment = null, $location = null, $service = null, $paymentAmount = null)
    {
        $patientName = $lead->name ?? ($appointment->patient->name ?? 'Unknown');
        $serviceName = $service->name ?? '';
        $locationName = '';
        if ($location) {
            $locationName = ($location->city->name ?? '') . '-' . ($location->name ?? '');
        }
        
        $description = '<span class="highlight-orange">' . ($serviceName ?: 'Service') . '</span> lead status changed to <span class="highlight-green">Converted</span> against <span class="highlight-orange">' . $patientName . '</span>' . ($locationName ? ' in <span class="highlight">' . $locationName . '</span>' : '');
        
        if ($paymentAmount) {
            $description .= ' with payment of <span class="highlight-green">PKR ' . number_format($paymentAmount) . '</span>';
        }
        
        return Activity::create([
            'account_id' => Auth::user()->account_id ?? $lead->account_id,
            'action' => 'Lead Converted',
            'activity_type' => 'lead_converted',
            'description' => $description,
            'patient' => $patientName,
            'patient_id' => $lead->patient_id ?? ($appointment->patient_id ?? null),
            'lead_id' => $lead->id,
            'lead_status' => 'Converted',
            'appointment_id' => $appointment->id ?? null,
            'service' => $serviceName,
            'service_id' => $service->id ?? null,
            'location' => $locationName,
            'centre_id' => $location->id ?? $lead->location_id ?? null,
            'amount' => $paymentAmount,
            'created_by' => Auth::user()->id ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Log appointment deleted activity (consultation or treatment)
     */
    public static function logAppointmentDeleted($appointment, $patient, $location = null, $service = null)
    {
        $patientName = $patient->name ?? 'Unknown';
        $creatorName = Auth::check() ? Auth::user()->name : 'System';
        $serviceName = $service->name ?? '';
        $locationName = '';
        if ($location) {
            $locationName = ($location->city->name ?? '') . '-' . ($location->name ?? '');
        }
        
        // Determine if consultation or treatment
        $appointmentType = $appointment->appointment_type_id == 1 ? 'Consultation' : 'Treatment';
        $activityType = $appointment->appointment_type_id == 1 ? 'consultation_deleted' : 'treatment_deleted';
        
        // Format: User deleted Patient's Service Consultation/Treatment in Location
        $description = '<span class="highlight">' . $creatorName . '</span> deleted <span class="highlight-orange">' . $patientName . '</span>\'s <span class="highlight-orange">' . ($serviceName ?: 'Service') . '</span> ' . $appointmentType;
        
        if ($locationName) {
            $description .= ' in <span class="highlight">' . $locationName . '</span>';
        }
        
        if ($appointment->scheduled_date) {
            $description .= ' scheduled on <span class="highlight-purple">' . date('M j, Y', strtotime($appointment->scheduled_date)) . '</span>';
        }
        
        return Activity::create([
            'account_id' => Auth::user()->account_id ?? $appointment->account_id,
            'action' => $appointmentType . ' Deleted',
            'activity_type' => $activityType,
            'description' => $description,
            'patient' => $patientName,
            'patient_id' => $patient->id ?? null,
            'appointment_id' => $appointment->id,
            'appointment_type' => $appointmentType,
            'service' => $serviceName,
            'service_id' => $service->id ?? $appointment->service_id,
            'location' => $locationName,
            'centre_id' => $location->id ?? $appointment->location_id,
            'schedule_date' => $appointment->scheduled_date,
            'created_by' => Auth::user()->id ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Log patient information updated activity
     */
    public static function logPatientUpdated($patient, $fieldChanges)
    {
        $patientName = $patient->name ?? 'Unknown';
        $creatorName = Auth::check() ? Auth::user()->name : 'System';
        
        // Build changes string
        $changesArr = [];
        foreach ($fieldChanges as $field => $change) {
            $changesArr[] = $field . ': <span class="highlight-purple">' . ($change['old'] ?? 'N/A') . '</span> → <span class="highlight-green">' . ($change['new'] ?? 'N/A') . '</span>';
        }
        $changesStr = implode(', ', $changesArr);
        
        $description = '<span class="highlight">' . $creatorName . '</span> updated <span class="highlight-orange">' . $patientName . '</span>\'s profile - ' . $changesStr;
        
        return Activity::create([
            'account_id' => Auth::user()->account_id ?? null,
            'action' => 'Patient Updated',
            'activity_type' => 'patient_updated',
            'description' => $description,
            'patient' => $patientName,
            'patient_id' => $patient->id,
            'created_by' => Auth::user()->id ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Log invoice cancelled activity
     */
    public static function logInvoiceCancelled($invoice, $patient, $location = null, $service = null, $appointmentType = null, $appointment = null)
    {
        $patientName = $patient->name ?? 'Unknown';
        $creatorName = Auth::check() ? Auth::user()->name : 'System';
        $serviceName = $service->name ?? '';
        $locationName = '';
        if ($location) {
            $locationName = ($location->name ?? '');
        }
        $amount = $invoice->total_price ?? 0;
        $apptType = $appointmentType->name ?? 'Consultation';
        
        // Get the treatment's scheduled date
        $scheduledDate = '';
        if ($appointment && $appointment->scheduled_date) {
            $scheduledDate = date('M j, Y', strtotime($appointment->scheduled_date));
        }
        
        $description = '<span class="highlight">' . $creatorName . '</span> cancelled invoice <span class="highlight-green">Rs. ' . number_format($amount) . '</span> for <span class="highlight-orange">' . $patientName . '</span>\'s <span class="highlight-orange">' . ($serviceName ?: $apptType) . '</span>' . ($locationName ? ' in <span class="highlight">' . $locationName . '</span>' : '') . ($scheduledDate ? ' scheduled on <span class="highlight-purple">' . $scheduledDate . '</span>' : '');
        
        return Activity::create([
            'account_id' => Auth::user()->account_id ?? $invoice->account_id,
            'action' => 'Invoice Cancelled',
            'activity_type' => 'invoice_cancelled',
            'description' => $description,
            'patient' => $patientName,
            'patient_id' => $patient->id ?? null,
            'appointment_id' => $invoice->appointment_id,
            'appointment_type' => $apptType,
            'service' => $serviceName,
            'service_id' => $service->id ?? null,
            'location' => $locationName,
            'centre_id' => $location->id ?? null,
            'amount' => $amount,
            'schedule_date' => $appointment->scheduled_date ?? null,
            'created_by' => Auth::user()->id ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Log voucher assigned activity
     * Format: USERNAME assigned VOUCHERNAME voucher of Rs. AMOUNT to PATIENTNAME
     */
    public static function logVoucherAssigned($userVoucher, $patient, $voucher)
    {
        $creatorName = Auth::user()->name ?? 'System';
        $patientName = $patient->name ?? 'Unknown';
        $voucherName = $voucher->name ?? 'Voucher';
        $amount = $userVoucher->amount ?? 0;
        
        $description = '<span class="highlight">' . $creatorName . '</span> assigned <span class="highlight-orange">' . $voucherName . '</span> voucher of <span class="highlight-green">Rs. ' . number_format($amount) . '</span> to <span class="highlight-orange">' . $patientName . '</span>';
        
        return Activity::create([
            'account_id' => Auth::user()->account_id ?? null,
            'action' => 'Voucher Assigned',
            'activity_type' => 'voucher_assigned',
            'description' => $description,
            'patient' => $patientName,
            'patient_id' => $patient->id ?? null,
            'amount' => $amount,
            'created_by' => Auth::user()->id ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Log voucher refunded/reset activity
     * Format: Rs. AMOUNT refunded to VOUCHERNAME voucher for PATIENTNAME
     */
    public static function logVoucherRefunded($amount, $patient, $voucher)
    {
        $creatorName = Auth::user()->name ?? 'System';
        $patientName = $patient->name ?? 'Unknown';
        $voucherName = $voucher->name ?? 'Voucher';
        
        $description = '<span class="highlight-green">Rs. ' . number_format($amount) . '</span> refunded to <span class="highlight-orange">' . $voucherName . '</span> voucher for <span class="highlight-orange">' . $patientName . '</span>';
        
        return Activity::create([
            'account_id' => Auth::user()->account_id ?? null,
            'action' => 'Voucher Refunded',
            'activity_type' => 'voucher_refunded',
            'description' => $description,
            'patient' => $patientName,
            'patient_id' => $patient->id ?? null,
            'amount' => $amount,
            'created_by' => Auth::user()->id ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Log voucher consumed activity
     * Format: Rs. AMOUNT consumed from VOUCHERNAME voucher against PATIENTNAME - Balance Left: Rs. BALANCE
     */
    public static function logVoucherConsumed($amount, $patient, $voucher, $balanceLeft = 0)
    {
        $creatorName = Auth::user()->name ?? 'System';
        $patientName = $patient->name ?? 'Unknown';
        $voucherName = $voucher->name ?? 'Voucher';
        
        $description = '<span class="highlight-green">Rs. ' . number_format($amount) . '</span> consumed from <span class="highlight-orange">' . $voucherName . '</span> voucher against <span class="highlight-orange">' . $patientName . '</span> - Balance Left: <span class="highlight-purple">Rs. ' . number_format($balanceLeft) . '</span>';
        
        return Activity::create([
            'account_id' => Auth::user()->account_id ?? null,
            'action' => 'Voucher Consumed',
            'activity_type' => 'voucher_consumed',
            'description' => $description,
            'patient' => $patientName,
            'patient_id' => $patient->id ?? null,
            'amount' => $amount,
            'created_by' => Auth::user()->id ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Log voucher updated activity
     * Format: USERNAME updated VOUCHERNAME voucher amount from Rs. OLDAMOUNT to Rs. NEWAMOUNT for PATIENTNAME
     */
    public static function logVoucherUpdated($userVoucher, $patient, $voucher, $oldAmount, $newAmount)
    {
        $creatorName = Auth::user()->name ?? 'System';
        $patientName = $patient->name ?? 'Unknown';
        $voucherName = $voucher->name ?? 'Voucher';
        
        $description = '<span class="highlight">' . $creatorName . '</span> updated <span class="highlight-orange">' . $voucherName . '</span> voucher amount from <span class="highlight-green">Rs. ' . number_format($oldAmount) . '</span> to <span class="highlight-green">Rs. ' . number_format($newAmount) . '</span> for <span class="highlight-orange">' . $patientName . '</span>';
        
        return Activity::create([
            'account_id' => Auth::user()->account_id ?? null,
            'action' => 'Voucher Updated',
            'activity_type' => 'voucher_updated',
            'description' => $description,
            'patient' => $patientName,
            'patient_id' => $patient->id ?? null,
            'amount' => $newAmount,
            'created_by' => Auth::user()->id ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Log feedback added activity
     * Format: USERNAME added feedback for SERVICENAME against PATIENTNAME scheduled on SCHEDULED DATE
     */
    public static function logFeedbackAdded($feedback, $appointment, $patient, $service, $location = null)
    {
        $locationName = '';
        if ($location) {
            $locationName = ($location->city->name ?? '') . '-' . ($location->name ?? '');
        }
        
        $serviceName = $service->name ?? '';
        $creatorName = Auth::user()->name ?? 'System';
        $patientName = $patient->name ?? 'Unknown';
        
        // Get scheduled date
        $scheduledDate = '';
        if ($appointment && $appointment->scheduled_date) {
            $scheduledDate = date('M j, Y', strtotime($appointment->scheduled_date));
        }
        
        $description = '<span class="highlight">' . $creatorName . '</span> added feedback for <span class="highlight-orange">' . ($serviceName ?: 'Service') . '</span>Treatment against <span class="highlight-orange">' . $patientName . '</span>' . ($scheduledDate ? ' scheduled on <span class="highlight-purple">' . $scheduledDate . '</span>' : '');
        
        return Activity::create([
            'account_id' => Auth::user()->account_id ?? null,
            'action' => 'Feedback Added',
            'activity_type' => 'feedback_added',
            'description' => $description,
            'patient' => $patientName,
            'patient_id' => $patient->id ?? null,
            'appointment_id' => $appointment->id ?? null,
            'appointment_type' => 'Treatment',
            'service' => $serviceName,
            'service_id' => $service->id ?? null,
            'location' => $locationName,
            'centre_id' => $location->id ?? null,
            'schedule_date' => $appointment->scheduled_date ?? null,
            'created_by' => Auth::user()->id ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
