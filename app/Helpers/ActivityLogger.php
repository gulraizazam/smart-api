<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Enums\AppointmentType;
use App\Models\Activity;
use App\Models\AppointmentLog;
use App\Models\Appointments;
use App\Models\AppointmentStatuses;
use App\Models\AppointmentTypes;
use App\Models\Discounts;
use App\Models\Feedback;
use App\Models\Invoices;
use App\Models\Leads;
use App\Models\Locations;
use App\Models\Membership;
use App\Models\MembershipType;
use App\Models\PackageAdvances;
use App\Models\Packages;
use App\Models\Patients;
use App\Models\Refunds;
use App\Models\Services;
use App\Models\UserVouchers;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class ActivityLogger
{
    /**
     * Round 4 Inj-C1 — sanitize an activity description before persisting
     * it to the database.
     *
     * Background: every method in this class concatenates user-supplied
     * name fragments (`$creatorName`, `$patientName`, `$serviceName`,
     * `$locationName`, `$package->name`, etc.) into an HTML template that
     * the activity log view at admin/reports/activity_logs/activities.blade.php
     * renders raw via `{!! $activity['message'] !!}`. An attacker who can
     * edit a patient/service/location name to include `<script>` lands a
     * stored XSS that fires for every viewer of the activity log.
     *
     * The fix is producer-side: tokenize the built description against a
     * tiny whitelist of known-safe markers (the highlight spans the
     * templates intentionally use) and HTML-escape everything else. The
     * blade view continues to use {!!} so the highlight markup keeps
     * rendering for legitimate activity rows. Old rows in the DB are
     * untouched — their existing markup still renders, and any latent
     * malicious payload there would only be cleaned up by a one-time
     * rewrite job (out of scope for this fix).
     */
    private static function sanitizeDescription(string $html): string
    {
        // Whitelisted CSS classes used by activity templates. Anything
        // else gets escaped — including bare <span> with no class.
        static $allowedClasses = [
            'highlight',
            'highlight-orange',
            'highlight-green',
            'highlight-purple',
            'highlight-blue',
            'highlight-red',
        ];

        $parts = preg_split(
            '#(<span\s+class="[^"]*">|</span>|<br\s*/?>)#i',
            $html,
            -1,
            PREG_SPLIT_DELIM_CAPTURE
        );

        if ($parts === false) {
            return htmlspecialchars($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        $out = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            if (preg_match('#^<span\s+class="([^"]*)">$#i', $part, $m)) {
                $cls = $m[1];
                if (in_array($cls, $allowedClasses, true)) {
                    $out .= '<span class="'.$cls.'">';
                } else {
                    // Unknown class — treat the whole tag as text.
                    $out .= htmlspecialchars($part, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                }

                continue;
            }

            if (strcasecmp($part, '</span>') === 0) {
                $out .= '</span>';

                continue;
            }

            if (preg_match('#^<br\s*/?>$#i', $part)) {
                $out .= '<br>';

                continue;
            }

            // Everything else is text from a user-controlled fragment —
            // escape it.
            $out .= htmlspecialchars($part, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        return $out;
    }

    /**
     * Log a lead created activity
     * Format: XYZ created a SERVICE_NAME lead for PATIENT_NAME in LOCATION_NAME
     */
    public static function logLeadCreated(Leads $lead, ?Locations $location = null, ?Services $service = null): Activity
    {
        $locationName = '';
        if ($location) {
            $locationName = ($location->city->name ?? '').'-'.($location->name ?? '');
        }

        $serviceName = $service->name ?? '';
        $creatorName = Auth::user()->name ?? 'System';
        $patientName = $lead->name ?? 'Unknown';

        // Format: XYZ created a SERVICE_NAME lead for PATIENT_NAME in LOCATION_NAME
        $description = '<span class="highlight">'.$creatorName.'</span> created a <span class="highlight-orange">'.($serviceName ?: 'Service').'</span> lead for <span class="highlight-orange">'.$patientName.'</span>'.($locationName ? ' in <span class="highlight">'.$locationName.'</span>' : '');

        return Activity::create([
            'account_id' => Auth::user()->account_id ?? $lead->account_id,
            'action' => 'Lead Created',
            'activity_type' => 'lead_created',
            'description' => self::sanitizeDescription($description),
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
    public static function logLeadBooked(Leads $lead, ?Appointments $appointment = null, ?Locations $location = null, ?Services $service = null): Activity
    {
        $locationName = '';
        if ($location) {
            $locationName = ($location->city->name ?? '').'-'.($location->name ?? '');
        }

        $serviceName = $service->name ?? '';
        $patientName = $lead->name ?? ($appointment->patient->name ?? 'Unknown');

        // Format: SERVICE_NAME lead status changed to LEAD_STATUS_NAME against PATIENT_NAME in LOCATION_NAME
        $description = '<span class="highlight-orange">'.($serviceName ?: 'Service').'</span> lead status changed to <span class="highlight-green">Booked</span> against <span class="highlight-orange">'.$patientName.'</span>'.($locationName ? ' in <span class="highlight">'.$locationName.'</span>' : '');

        return Activity::create([
            'account_id' => Auth::user()->account_id ?? $lead->account_id,
            'action' => 'Lead Booked',
            'activity_type' => 'lead_booked',
            'description' => self::sanitizeDescription($description),
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
    public static function logLeadArrived(Leads $lead, ?Appointments $appointment = null, ?Invoices $invoice = null, ?Locations $location = null, ?Services $service = null): Activity
    {
        $locationName = '';
        if ($location) {
            $locationName = ($location->city->name ?? '').'-'.($location->name ?? '');
        }

        $serviceName = $service->name ?? '';
        $patientName = $lead->name ?? ($appointment->patient->name ?? 'Unknown');
        $amount = $invoice->total_price ?? 0;

        // Format: SERVICE_NAME lead status changed to LEAD_STATUS_NAME for PATIENT_NAME in LOCATION_NAME
        $description = '<span class="highlight-orange">'.($serviceName ?: 'Service').'</span> lead status changed to <span class="highlight-green">Arrived</span> for <span class="highlight-orange">'.$patientName.'</span>'.($locationName ? ' in <span class="highlight">'.$locationName.'</span>' : '');

        return Activity::create([
            'account_id' => Auth::user()->account_id ?? $lead->account_id,
            'action' => 'Lead Arrived',
            'activity_type' => 'lead_arrived',
            'description' => self::sanitizeDescription($description),
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
    public static function logConsultationBooked(Appointments $appointment, Patients $patient, ?Locations $location = null, ?Services $service = null): Activity
    {
        $locationName = '';
        if ($location) {
            $locationName = ($location->city->name ?? '').'-'.($location->name ?? '');
        }

        $serviceName = $service->name ?? '';

        return Activity::create([
            'account_id' => Auth::user()->account_id ?? $appointment->account_id,
            'action' => 'Consultation Booked',
            'activity_type' => 'consultation_booked',
            'description' => self::sanitizeDescription('Consultation Booked for '.($patient->name ?? 'Unknown').($serviceName ? ' - '.$serviceName : '').($locationName ? ' at '.$locationName : '')),
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
    public static function logTreatmentBooked(Appointments $appointment, Patients $patient, ?Locations $location = null, ?Services $service = null): Activity
    {
        $locationName = '';
        if ($location) {
            $locationName = ($location->city->name ?? '').'-'.($location->name ?? '');
        }

        $serviceName = $service->name ?? '';
        $patientName = $patient->name ?? 'Unknown';
        $creatorName = \Auth::user()->name ?? 'System';
        $scheduleDate = $appointment->scheduled_date ? date('M j, Y', strtotime((string) $appointment->scheduled_date)) : '';

        // Format: XYZ booked SERVICE_NAME Treatment for PATIENT_NAME in LOCATION_NAME on DATE
        $description = '<span class="highlight">'.$creatorName.'</span> booked <span class="highlight-orange">'.($serviceName ?: 'Service').'</span> Treatment for <span class="highlight-orange">'.$patientName.'</span>'.($locationName ? ' in <span class="highlight">'.$locationName.'</span>' : '').($scheduleDate ? ' on <span class="highlight-purple">'.$scheduleDate.'</span>' : '');

        return Activity::create([
            'account_id' => Auth::user()->account_id ?? $appointment->account_id,
            'action' => 'Treatment Booked',
            'activity_type' => 'treatment_booked',
            'description' => self::sanitizeDescription($description),
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
    public static function logPackageCreated(Packages $package, Patients $patient, ?Locations $location = null): Activity
    {
        $locationName = '';
        if ($location) {
            $locationName = ($location->city->name ?? '').'-'.($location->name ?? '');
        }

        return Activity::create([
            'account_id' => Auth::user()->account_id ?? $package->account_id,
            'action' => 'Package Created',
            'activity_type' => 'package_created',
            'description' => self::sanitizeDescription('Package Created - Plan Id: '.$package->id.' ('.($package->name ?? 'Package').') for Rs. '.number_format($package->total_price).($locationName ? ' at '.$locationName : '')),
            'patient' => $patient->name ?? '',
            'patient_id' => $patient->id ?? $package->patient_id,
            'plan_id' => $package->id,
            'package_id' => $package->id,
            'amount' => $package->total_price,
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
    public static function logPaymentReceived(PackageAdvances $payment, Packages $package, Patients $patient, ?Locations $location = null): Activity
    {
        $locationName = '';
        if ($location) {
            $locationName = ($location->city->name ?? '').'-'.($location->name ?? '');
        }

        $patientId = $patient->id ?? $package->patient_id ?? null;
        $createdById = Auth::check() ? Auth::user()->id : null;
        $creatorName = Auth::check() ? Auth::user()->name : 'System';
        $patientName = $patient->name ?? 'Unknown';

        // Format description with highlights
        $description = '<span class="highlight">'.$creatorName.'</span> received <span class="highlight-green">Rs. '.number_format($payment->cash_amount).'</span> from <span class="highlight-orange">'.$patientName.'</span> for <span class="highlight-orange">Plan Id: '.$package->id.'</span>'.($locationName ? ' in <span class="highlight">'.$locationName.'</span>' : '');

        return Activity::create([
            'account_id' => Auth::user()->account_id ?? $package->account_id,
            'action' => 'Payment Received',
            'activity_type' => 'payment_received',
            'description' => self::sanitizeDescription($description),
            'patient' => $patientName,
            'patient_id' => $patientId,
            'plan_id' => $package->id,
            'package_id' => $package->id,
            'amount' => $payment->cash_amount,
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
    public static function logPaymentUpdated(float|int $oldAmount, float|int $newAmount, \DateTimeInterface|string|null $oldDate, \DateTimeInterface|string|null $newDate, bool $amountChanged, bool $dateChanged, Packages $package, Patients $patient, ?Locations $location = null): Activity
    {
        $locationName = '';
        if ($location) {
            $locationName = ($location->city->name ?? '').'-'.($location->name ?? '');
        }

        $patientId = $patient->id ?? $package->patient_id ?? null;
        $createdById = Auth::check() ? Auth::user()->id : null;
        $creatorName = Auth::check() ? Auth::user()->name : 'System';
        $patientName = $patient->name ?? 'Unknown';

        // Format dates for display
        $oldDateFormatted = $oldDate ? date('M j, Y', strtotime((string) $oldDate)) : '';
        $newDateFormatted = $newDate ? date('M j, Y', strtotime((string) $newDate)) : '';

        // Build description based on what changed
        $description = '<span class="highlight">'.$creatorName.'</span>';

        if ($amountChanged && $dateChanged) {
            // Both amount and date changed
            $description .= ' changed payment amount from <span class="highlight-purple">Rs. '.number_format($oldAmount).'</span> to <span class="highlight-green">Rs. '.number_format($newAmount).'</span> and payment received date from <span class="highlight-purple">'.$oldDateFormatted.'</span> to <span class="highlight-green">'.$newDateFormatted.'</span>';
        } elseif ($dateChanged) {
            // Only date changed
            $description .= ' changed payment received date from <span class="highlight-purple">'.$oldDateFormatted.'</span> to <span class="highlight-green">'.$newDateFormatted.'</span> for <span class="highlight-green">Rs. '.number_format($newAmount).'</span>';
        } else {
            // Only amount changed
            $description .= ' updated payment from <span class="highlight-purple">Rs. '.number_format($oldAmount).'</span> to <span class="highlight-green">Rs. '.number_format($newAmount).'</span>';
        }

        $description .= ' from <span class="highlight-orange">'.$patientName.'</span> for <span class="highlight-orange">Plan Id: '.$package->id.'</span>'.($locationName ? ' in <span class="highlight">'.$locationName.'</span>' : '');

        return Activity::create([
            'account_id' => Auth::user()->account_id ?? $package->account_id,
            'action' => 'Payment Updated',
            'activity_type' => 'payment_updated',
            'description' => self::sanitizeDescription($description),
            'patient' => $patientName,
            'patient_id' => $patientId,
            'plan_id' => $package->id,
            'package_id' => $package->id,
            'amount' => $newAmount,
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
    public static function logPaymentDeleted(float|int $amount, Packages $package, Patients $patient, ?Locations $location = null): Activity
    {
        $locationName = '';
        if ($location) {
            $locationName = ($location->city->name ?? '').'-'.($location->name ?? '');
        }

        $patientId = $patient->id ?? $package->patient_id ?? null;
        $createdById = Auth::check() ? Auth::user()->id : null;
        $creatorName = Auth::check() ? Auth::user()->name : 'System';
        $patientName = $patient->name ?? 'Unknown';

        // Format description with highlights (no date - timestamp shows when it happened)
        $description = '<span class="highlight">'.$creatorName.'</span> deleted <span class="highlight-green">Rs. '.number_format($amount).'</span> payment from <span class="highlight-orange">Plan Id: '.$package->id.'</span> against <span class="highlight-orange">'.$patientName.'</span>'.($locationName ? ' in <span class="highlight">'.$locationName.'</span>' : '');

        return Activity::create([
            'account_id' => Auth::user()->account_id ?? $package->account_id,
            'action' => 'Payment Deleted',
            'activity_type' => 'payment_deleted',
            'description' => self::sanitizeDescription($description),
            'patient' => $patientName,
            'patient_id' => $patientId,
            'plan_id' => $package->id,
            'package_id' => $package->id,
            'amount' => $amount,
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
    public static function logRefundMade(Refunds $refund, Packages $package, Patients $patient, ?Locations $location = null): Activity
    {
        $locationName = '';
        if ($location) {
            $locationName = ($location->city->name ?? '').'-'.($location->name ?? '');
        }

        return Activity::create([
            'account_id' => Auth::user()->account_id ?? $package->account_id,
            'action' => 'Refund Made',
            'activity_type' => 'refund_made',
            'log_tier' => \App\Enums\ActivityLogTier::PhiAudit->value,
            'description' => self::sanitizeDescription('Refund Made Rs. '.number_format($refund->cash_amount).' to '.($patient->name ?? 'Unknown').' for Plan Id: '.$package->id.($locationName ? ' at '.$locationName : '')),
            'patient' => $patient->name ?? '',
            'patient_id' => $patient->id ?? $package->patient_id,
            'plan_id' => $package->id,
            'package_id' => $package->id,
            'amount' => $refund->cash_amount,
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
    public static function logAppointmentRescheduled(Appointments $appointment, Patients $patient, \DateTimeInterface|string $oldDate, \DateTimeInterface|string $oldTime, \DateTimeInterface|string $newDate, \DateTimeInterface|string $newTime, ?Locations $location = null, ?Services $service = null): Activity
    {
        $locationName = '';
        if ($location) {
            $locationName = ($location->city->name ?? '').'-'.($location->name ?? '');
        }

        $serviceName = $service->name ?? '';
        $patientName = $patient->name ?? 'Unknown';
        $creatorName = \Auth::user()->name ?? 'System';

        // Determine appointment type
        $appointmentType = $appointment->appointment_type_id === AppointmentType::Consultancy->value ? 'Consultation' : 'Treatment';

        // Format old and new datetime
        $oldDateTime = date('M j, Y', strtotime((string) $oldDate)).' '.date('h:i A', strtotime((string) $oldTime));
        $newDateTime = date('M j, Y', strtotime((string) $newDate)).' '.date('h:i A', strtotime((string) $newTime));

        // Format: XYZ rescheduled SERVICE_NAME APPOINTMENT_TYPE for PATIENT_NAME from OLD_DATETIME to NEW_DATETIME in LOCATION_NAME
        $description = '<span class="highlight">'.$creatorName.'</span> rescheduled <span class="highlight-orange">'.($serviceName ?: 'Service').'</span> '.$appointmentType.' for <span class="highlight-orange">'.$patientName.'</span> from <span class="highlight-purple">'.$oldDateTime.'</span> to <span class="highlight-green">'.$newDateTime.'</span>'.($locationName ? ' in <span class="highlight">'.$locationName.'</span>' : '');

        return Activity::create([
            'account_id' => \Auth::user()->account_id ?? null,
            'action' => 'rescheduled',
            'activity_type' => 'appointment_rescheduled',
            'description' => self::sanitizeDescription($description),
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
    public static function logAppointmentStatusChange(Appointments $appointment, Patients $patient, AppointmentStatuses $oldStatus, AppointmentStatuses $newStatus, ?Locations $location = null, ?Services $service = null): Activity
    {
        $locationName = '';
        if ($location) {
            $locationName = ($location->city->name ?? '').'-'.($location->name ?? '');
        }

        $serviceName = $service->name ?? '';
        $patientName = $patient->name ?? 'Unknown';
        $oldStatusName = $oldStatus->name ?? 'Unknown';
        $newStatusName = $newStatus->name ?? 'Unknown';
        $creatorName = \Auth::user()->name ?? 'System';

        // Determine appointment type
        $appointmentType = $appointment->appointment_type_id === AppointmentType::Consultancy->value ? 'Consultation' : 'Treatment';

        // Format: XYZ changed SERVICE_NAME APPOINTMENT_TYPE status from OLD_STATUS to NEW_STATUS for PATIENT_NAME in LOCATION_NAME
        $description = '<span class="highlight">'.$creatorName.'</span> changed <span class="highlight-orange">'.($serviceName ?: 'Service').'</span> '.$appointmentType.' status from <span class="highlight-purple">'.$oldStatusName.'</span> to <span class="highlight-green">'.$newStatusName.'</span> for <span class="highlight-orange">'.$patientName.'</span>'.($locationName ? ' in <span class="highlight">'.$locationName.'</span>' : '');

        return Activity::create([
            'account_id' => \Auth::user()->account_id ?? null,
            'action' => 'Status Changed',
            'activity_type' => 'appointment_status_changed',
            'description' => self::sanitizeDescription($description),
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
    public static function logAppointmentUpdated(Appointments $appointment, Patients $patient, array $changes, ?Locations $location = null, ?Services $service = null): ?Activity
    {
        if (empty($changes)) {
            return null;
        }

        $locationName = '';
        if ($location) {
            $locationName = ($location->city->name ?? '').'-'.($location->name ?? '');
        }

        $serviceName = $service->name ?? '';
        $patientName = $patient->name ?? 'Unknown';
        $creatorName = \Auth::user()->name ?? 'System';

        // Determine appointment type
        $appointmentType = $appointment->appointment_type_id === AppointmentType::Consultancy->value ? 'Consultation' : 'Treatment';

        // Build changes description - shorter format
        $changeDescriptions = [];
        foreach ($changes as $field => $change) {
            $changeDescriptions[] = $field.': <span class="highlight-purple">'.$change['old'].'</span> → <span class="highlight-green">'.$change['new'].'</span>';
        }

        $changesText = implode(', ', $changeDescriptions);

        // Format: XYZ updated PATIENT_NAME's SERVICE APPOINTMENT_TYPE - CHANGES in LOCATION
        $description = '<span class="highlight">'.$creatorName.'</span> updated <span class="highlight-orange">'.$patientName.'</span>\'s <span class="highlight-orange">'.($serviceName ?: 'Service').'</span> '.$appointmentType.' - '.$changesText.($locationName ? ' in <span class="highlight">'.$locationName.'</span>' : '');

        return Activity::create([
            'account_id' => \Auth::user()->account_id ?? null,
            'action' => 'Appointment Updated',
            'activity_type' => 'appointment_updated',
            'description' => self::sanitizeDescription($description),
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
    public static function logInvoiceCreated(Invoices $invoice, Appointments $appointment, Patients $patient, ?Locations $location = null, ?Services $service = null): Activity
    {
        $locationName = '';
        if ($location) {
            $locationName = ($location->city->name ?? '').'-'.($location->name ?? '');
        }

        $serviceName = $service->name ?? '';

        return Activity::create([
            'account_id' => Auth::user()->account_id ?? $invoice->account_id,
            'action' => 'Invoice Created',
            'activity_type' => 'invoice_created',
            'description' => self::sanitizeDescription('Invoice Created Rs. '.number_format($invoice->total_price).' for '.($serviceName ?: 'Consultation').($locationName ? ' at '.$locationName : '')),
            'patient' => $patient->name ?? '',
            'patient_id' => $patient->id ?? $invoice->patient_id,
            'appointment_id' => $appointment->id ?? $invoice->appointment_id,
            'invoice_id' => $invoice->id,
            'amount' => $invoice->total_price,
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
    public static function logMembershipAssigned(Patients $patient, Membership $membership, ?MembershipType $membershipType = null): Activity
    {
        $patientName = $patient->name ?? 'Unknown';
        $creatorName = Auth::check() ? Auth::user()->name : 'System';
        $membershipCode = $membership->code ?? '';
        $membershipTypeName = $membershipType->name ?? ($membership->membershipType->name ?? 'Membership');

        $description = '<span class="highlight">'.$creatorName.'</span> assigned <span class="highlight-green">'.$membershipTypeName.'</span> membership (Code: <span class="highlight-orange">'.$membershipCode.'</span>) to <span class="highlight-orange">'.$patientName.'</span>';

        return Activity::create([
            'account_id' => Auth::user()->account_id ?? null,
            'action' => 'Membership Assigned',
            'activity_type' => 'membership_assigned',
            'description' => self::sanitizeDescription($description),
            'patient' => $patientName,
            'patient_id' => $patient->id,
            'created_by' => Auth::user()->id ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Log membership renewed activity
     *
     * MembershipRenewalService was calling this method before it
     * existed — any production renewal would have crashed at the
     * activity-log step. The method signature mirrors the assigned /
     * cancelled helpers: Patients $patient (NOT User, which is a
     * different class hierarchy), the original and renewed membership
     * rows, and an optional type for display.
     */
    public static function logMembershipRenewed(Patients $patient, Membership $oldMembership, Membership $newMembership, ?MembershipType $membershipType = null): Activity
    {
        $patientName = $patient->name ?? 'Unknown';
        $creatorName = Auth::check() ? Auth::user()->name : 'System';
        $oldCode = $oldMembership->code ?? '';
        $newCode = $newMembership->code ?? '';
        $membershipTypeName = $membershipType->name ?? ($newMembership->membershipType->name ?? 'Membership');

        $description = '<span class="highlight">'.$creatorName.'</span> renewed <span class="highlight-green">'.$membershipTypeName.'</span> membership (<span class="highlight-orange">'.$oldCode.'</span> → <span class="highlight-orange">'.$newCode.'</span>) for <span class="highlight-orange">'.$patientName.'</span>';

        return Activity::create([
            'account_id' => Auth::user()->account_id ?? null,
            'action' => 'Membership Renewed',
            'activity_type' => 'membership_renewed',
            'description' => self::sanitizeDescription($description),
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
    public static function logMembershipCancelled(Patients $patient, Membership $membership, ?MembershipType $membershipType = null): Activity
    {
        $patientName = $patient->name ?? 'Unknown';
        $creatorName = Auth::check() ? Auth::user()->name : 'System';
        $membershipCode = $membership->code ?? '';
        $membershipTypeName = $membershipType->name ?? ($membership->membershipType->name ?? 'Membership');

        $description = '<span class="highlight">'.$creatorName.'</span> cancelled <span class="highlight-purple">'.$membershipTypeName.'</span> membership (Code: <span class="highlight-orange">'.$membershipCode.'</span>) for <span class="highlight-orange">'.$patientName.'</span>';

        return Activity::create([
            'account_id' => Auth::user()->account_id ?? null,
            'action' => 'Membership Cancelled',
            'activity_type' => 'membership_cancelled',
            'description' => self::sanitizeDescription($description),
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
    public static function logAppointmentConverted(Appointments $appointment, Patients $patient, ?Locations $location = null, ?Services $service = null, float|int|null $paymentAmount = null, ?int $planId = null): Activity
    {
        $patientName = $patient->name ?? 'Unknown';
        $serviceName = $service->name ?? '';
        $locationName = '';
        if ($location) {
            $locationName = ($location->city->name ?? '').'-'.($location->name ?? '');
        }

        // Format: Patient's Service Consultation status changed to converted with the payment of Rs X in PlanID
        $description = '<span class="highlight-orange">'.$patientName.'</span>\'s <span class="highlight-orange">'.($serviceName ?: 'Service').'</span> Consultation status changed to <span class="highlight-green">Converted</span>';

        if ($paymentAmount) {
            $description .= ' with the payment of <span class="highlight-green">Rs '.number_format($paymentAmount).'</span>';
        }

        if ($planId) {
            $description .= ' in <span class="highlight-purple">Plan #'.sprintf('%05d', $planId).'</span>';
        }

        return Activity::create([
            'account_id' => Auth::user()->account_id ?? $appointment->account_id,
            'action' => 'Appointment Converted',
            'activity_type' => 'appointment_converted',
            'description' => self::sanitizeDescription($description),
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
    public static function logLeadConverted(Leads $lead, ?Appointments $appointment = null, ?Locations $location = null, ?Services $service = null, float|int|null $paymentAmount = null): Activity
    {
        $patientName = $lead->name ?? ($appointment->patient->name ?? 'Unknown');
        $serviceName = $service->name ?? '';
        $locationName = '';
        if ($location) {
            $locationName = ($location->city->name ?? '').'-'.($location->name ?? '');
        }

        $description = '<span class="highlight-orange">'.($serviceName ?: 'Service').'</span> lead status changed to <span class="highlight-green">Converted</span> against <span class="highlight-orange">'.$patientName.'</span>'.($locationName ? ' in <span class="highlight">'.$locationName.'</span>' : '');

        if ($paymentAmount) {
            $description .= ' with payment of <span class="highlight-green">PKR '.number_format($paymentAmount).'</span>';
        }

        return Activity::create([
            'account_id' => Auth::user()->account_id ?? $lead->account_id,
            'action' => 'Lead Converted',
            'activity_type' => 'lead_converted',
            'description' => self::sanitizeDescription($description),
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
    public static function logAppointmentDeleted(Appointments $appointment, Patients $patient, ?Locations $location = null, ?Services $service = null): Activity
    {
        $patientName = $patient->name ?? 'Unknown';
        $creatorName = Auth::check() ? Auth::user()->name : 'System';
        $serviceName = $service->name ?? '';
        $locationName = '';
        if ($location) {
            $locationName = ($location->city->name ?? '').'-'.($location->name ?? '');
        }

        // Determine if consultation or treatment
        $appointmentType = $appointment->appointment_type_id === AppointmentType::Consultancy->value ? 'Consultation' : 'Treatment';
        $activityType = $appointment->appointment_type_id === AppointmentType::Consultancy->value ? 'consultation_deleted' : 'treatment_deleted';

        // Format: User deleted Patient's Service Consultation/Treatment in Location
        $description = '<span class="highlight">'.$creatorName.'</span> deleted <span class="highlight-orange">'.$patientName.'</span>\'s <span class="highlight-orange">'.($serviceName ?: 'Service').'</span> '.$appointmentType;

        if ($locationName) {
            $description .= ' in <span class="highlight">'.$locationName.'</span>';
        }

        if ($appointment->scheduled_date) {
            $description .= ' scheduled on <span class="highlight-purple">'.date('M j, Y', strtotime((string) $appointment->scheduled_date)).'</span>';
        }

        return Activity::create([
            'account_id' => Auth::user()->account_id ?? $appointment->account_id,
            'action' => $appointmentType.' Deleted',
            'activity_type' => $activityType,
            'description' => self::sanitizeDescription($description),
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
    public static function logPatientUpdated(Patients $patient, array $fieldChanges): Activity
    {
        $patientName = $patient->name ?? 'Unknown';
        $creatorName = Auth::check() ? Auth::user()->name : 'System';

        // Build changes string
        $changesArr = [];
        foreach ($fieldChanges as $field => $change) {
            $changesArr[] = $field.': <span class="highlight-purple">'.($change['old'] ?? 'N/A').'</span> → <span class="highlight-green">'.($change['new'] ?? 'N/A').'</span>';
        }
        $changesStr = implode(', ', $changesArr);

        $description = '<span class="highlight">'.$creatorName.'</span> updated <span class="highlight-orange">'.$patientName.'</span>\'s profile - '.$changesStr;

        return Activity::create([
            'account_id' => Auth::user()->account_id ?? null,
            'action' => 'Patient Updated',
            'activity_type' => 'patient_updated',
            'description' => self::sanitizeDescription($description),
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
    public static function logInvoiceCancelled(Invoices $invoice, Patients $patient, ?Locations $location = null, ?Services $service = null, ?AppointmentTypes $appointmentType = null, ?Appointments $appointment = null): Activity
    {
        $patientName = $patient->name ?? 'Unknown';
        $creatorName = Auth::check() ? Auth::user()->name : 'System';
        $serviceName = $service->name ?? '';
        $locationName = '';
        if ($location) {
            $locationName = ($location->name ?? '');
        }
        // Cast: invoices.total_price is decimal(11,2) which Laravel hands
        // back as a string. PHP 8.4's stricter number_format() signature
        // refuses string arguments, so a bare `number_format($amount)`
        // call here throws TypeError mid-cancel — leaving the cancel half-
        // committed inside the surrounding DB::transaction.
        $amount = (float) ($invoice->total_price ?? 0);
        $apptType = $appointmentType->name ?? 'Consultation';

        // Get the treatment's scheduled date
        $scheduledDate = '';
        if ($appointment && $appointment->scheduled_date) {
            $scheduledDate = date('M j, Y', strtotime((string) $appointment->scheduled_date));
        }

        $description = '<span class="highlight">'.$creatorName.'</span> cancelled invoice <span class="highlight-green">Rs. '.number_format($amount).'</span> for <span class="highlight-orange">'.$patientName.'</span>\'s <span class="highlight-orange">'.($serviceName ?: $apptType).'</span>'.($locationName ? ' in <span class="highlight">'.$locationName.'</span>' : '').($scheduledDate ? ' scheduled on <span class="highlight-purple">'.$scheduledDate.'</span>' : '');

        return Activity::create([
            'account_id' => Auth::user()->account_id ?? $invoice->account_id,
            'action' => 'Invoice Cancelled',
            'activity_type' => 'invoice_cancelled',
            'description' => self::sanitizeDescription($description),
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
     *
     * The $voucher parameter is a `Discounts` row with
     * `discount_type = 'voucher'`. Despite the name, vouchers are NOT
     * stored in the legacy `vouchers` table (the `Voucher` model) —
     * they live in `discounts`, which is what every production caller
     * passes here. The original `Voucher` type hint was a typo that
     * triggered a TypeError on every real invocation.
     */
    public static function logVoucherAssigned(UserVouchers $userVoucher, Patients $patient, Discounts $voucher): Activity
    {
        $creatorName = Auth::user()->name ?? 'System';
        $patientName = $patient->name ?? 'Unknown';
        $voucherName = $voucher->name ?? 'Voucher';
        // `user_vouchers.amount` is a DECIMAL column that MariaDB returns
        // as a string; number_format() in PHP 8.4 rejects strings under
        // strict_types. Cast to float before formatting.
        $amount = (float) ($userVoucher->amount ?? 0);

        $description = '<span class="highlight">'.$creatorName.'</span> assigned <span class="highlight-orange">'.$voucherName.'</span> voucher of <span class="highlight-green">Rs. '.number_format($amount).'</span> to <span class="highlight-orange">'.$patientName.'</span>';

        return Activity::create([
            'account_id' => Auth::user()->account_id ?? null,
            'action' => 'Voucher Assigned',
            'activity_type' => 'voucher_assigned',
            'description' => self::sanitizeDescription($description),
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
     *
     * Same rationale as logVoucherAssigned — $voucher is a `Discounts`
     * row, not a `Voucher`. See PlanDiscountService line ~363 for the
     * call site that exposes this.
     */
    public static function logVoucherRefunded(float|int $amount, Patients $patient, Discounts $voucher): Activity
    {
        $creatorName = Auth::user()->name ?? 'System';
        $patientName = $patient->name ?? 'Unknown';
        $voucherName = $voucher->name ?? 'Voucher';

        $description = '<span class="highlight-green">Rs. '.number_format($amount).'</span> refunded to <span class="highlight-orange">'.$voucherName.'</span> voucher for <span class="highlight-orange">'.$patientName.'</span>';

        return Activity::create([
            'account_id' => Auth::user()->account_id ?? null,
            'action' => 'Voucher Refunded',
            'activity_type' => 'voucher_refunded',
            'description' => self::sanitizeDescription($description),
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
     *
     * Same rationale as logVoucherAssigned — $voucher is a `Discounts`
     * row. PlanService line ~1992 passes a `Discounts::find()` result
     * here, so the original `Voucher` type hint would have TypeError'd
     * on every real call.
     */
    public static function logVoucherConsumed(float|int $amount, Patients $patient, Discounts $voucher, float|int $balanceLeft = 0): Activity
    {
        $creatorName = Auth::user()->name ?? 'System';
        $patientName = $patient->name ?? 'Unknown';
        $voucherName = $voucher->name ?? 'Voucher';

        $description = '<span class="highlight-green">Rs. '.number_format($amount).'</span> consumed from <span class="highlight-orange">'.$voucherName.'</span> voucher against <span class="highlight-orange">'.$patientName.'</span> - Balance Left: <span class="highlight-purple">Rs. '.number_format($balanceLeft).'</span>';

        return Activity::create([
            'account_id' => Auth::user()->account_id ?? null,
            'action' => 'Voucher Consumed',
            'activity_type' => 'voucher_consumed',
            'description' => self::sanitizeDescription($description),
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
     *
     * Same rationale as logVoucherAssigned — $voucher is a `Discounts`
     * row, not a `Voucher`.
     */
    public static function logVoucherUpdated(UserVouchers $userVoucher, Patients $patient, Discounts $voucher, float|int $oldAmount, float|int $newAmount): Activity
    {
        $creatorName = Auth::user()->name ?? 'System';
        $patientName = $patient->name ?? 'Unknown';
        $voucherName = $voucher->name ?? 'Voucher';

        $description = '<span class="highlight">'.$creatorName.'</span> updated <span class="highlight-orange">'.$voucherName.'</span> voucher amount from <span class="highlight-green">Rs. '.number_format($oldAmount).'</span> to <span class="highlight-green">Rs. '.number_format($newAmount).'</span> for <span class="highlight-orange">'.$patientName.'</span>';

        return Activity::create([
            'account_id' => Auth::user()->account_id ?? null,
            'action' => 'Voucher Updated',
            'activity_type' => 'voucher_updated',
            'description' => self::sanitizeDescription($description),
            'patient' => $patientName,
            'patient_id' => $patient->id ?? null,
            'amount' => $newAmount,
            'created_by' => Auth::user()->id ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Save appointment log entry to AppointmentLog model.
     * Extracted from GeneralFunctions::saveAppointmentLogs.
     */
    public static function saveAppointmentLogs(string $action, string $screen, Model $data): void
    {
        try {
            AppointmentLog::create([
                'user_id' => auth()->id(),
                'action_by' => auth()->user()->name ?? 'Admin',
                'action_for' => $data->name ?? '',
                'action' => $action,
                'screen' => $screen,
                // Nullsafe — Locations::find() returns null when the id doesn't
                // exist (or is 0), and `->name` on null was a latent fatal.
                'address' => Locations::find($data->location_id ?? 0)?->name ?? '',
                'date' => Carbon::now()->timezone('Asia/Karachi')->format('Y-m-d'),
                'time' => Carbon::now()->timezone('Asia/Karachi')->format('H:i:s'),
                'type' => $action,
            ]);
        } catch (\Exception $e) {
            //
        }
    }

    /**
     * Save activity log entry to Activity model.
     * Extracted from GeneralFunctions::saveActivityLogs.
     */
    public static function saveActivityLogs(string $action, string $activityType, array $data, int $appointment_id): bool
    {
        try {
            $location = Locations::find($data['location_id']);
            $service = Services::find($data['service_id']);
            $patient = Patients::find($data['patient_id']);

            Activity::create([
                'created_by' => auth()->id(),
                'user_id' => auth()->id(),
                'action' => $action,
                'appointment_type' => $activityType,
                'appointment_id' => $appointment_id,
                'activity_type' => $activityType,
                'location' => $location?->name ?? '',
                'centre_id' => $location?->id,
                'service_id' => $service?->id,
                'service' => $service?->name,
                'patient_id' => $patient ? $patient->id : null,
                'patient' => $patient ? $patient->name : null,
                'schedule_date' => $data['scheduled_date'],
                'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Log feedback added activity
     * Format: USERNAME added feedback for SERVICENAME against PATIENTNAME scheduled on SCHEDULED DATE
     */
    public static function logFeedbackAdded(Feedback $feedback, Appointments $appointment, Patients $patient, Services $service, ?Locations $location = null): Activity
    {
        $locationName = '';
        if ($location) {
            $locationName = ($location->city->name ?? '').'-'.($location->name ?? '');
        }

        $serviceName = $service->name ?? '';
        $creatorName = Auth::user()->name ?? 'System';
        $patientName = $patient->name ?? 'Unknown';

        // Get scheduled date
        $scheduledDate = '';
        if ($appointment && $appointment->scheduled_date) {
            $scheduledDate = date('M j, Y', strtotime((string) $appointment->scheduled_date));
        }

        $description = '<span class="highlight">'.$creatorName.'</span> added feedback for <span class="highlight-orange">'.($serviceName ?: 'Service').'</span>Treatment against <span class="highlight-orange">'.$patientName.'</span>'.($scheduledDate ? ' scheduled on <span class="highlight-purple">'.$scheduledDate.'</span>' : '');

        return Activity::create([
            'account_id' => Auth::user()->account_id ?? null,
            'action' => 'Feedback Added',
            'activity_type' => 'feedback_added',
            'description' => self::sanitizeDescription($description),
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

    // =====================================================================
    // HIPAA-aligned writers (new in the compact-renderer PR).
    //
    // These produce Activity rows for events that previously left no audit
    // trail at all: patient record access, data exports, cashflow period
    // locks, discount application to invoices, and admin-initiated SMS/
    // email. Each row lands at the correct tier so retention is governed
    // automatically by the archive command.
    // =====================================================================

    /**
     * Log a patient-record access (read). Throttled to one row per
     * (user, patient) per access_throttle_minutes window to keep the
     * feed usable — a clinician opening a file multiple times during a
     * consultation produces one row, not dozens.
     *
     * Returns null when the throttle suppresses the write (already-logged
     * access within the window). Callers should treat null as "silent
     * success," not failure.
     */
    public static function logPatientAccessed(Patients $patient, ?Locations $location = null): ?Activity
    {
        $userId = Auth::id();
        if ($userId === null || $patient->id === null) {
            return null;
        }

        $throttleMinutes = (int) config('activity_log.access_throttle_minutes', 1440);
        $since = now()->subMinutes($throttleMinutes);

        $recentExists = Activity::where('activity_type', 'patient_accessed')
            ->where('patient_id', $patient->id)
            ->where('created_by', $userId)
            ->where('created_at', '>=', $since)
            ->exists();

        if ($recentExists) {
            return null;
        }

        $locationName = '';
        if ($location) {
            $locationName = ($location->city->name ?? '').'-'.($location->name ?? '');
        }

        $patientName = $patient->name ?? 'Unknown';
        $actorName = Auth::user()->name ?? 'System';

        return Activity::create([
            'account_id' => Auth::user()->account_id ?? null,
            'action' => 'viewed',
            'activity_type' => 'patient_accessed',
            'log_tier' => 'phi_audit',
            'description' => self::sanitizeDescription(
                '<span class="highlight">'.$actorName.'</span> viewed <span class="highlight-orange">'.$patientName.'</span>\'s record'
                .($locationName ? ' at <span class="highlight">'.$locationName.'</span>' : '')
            ),
            'patient' => $patientName,
            'patient_id' => $patient->id,
            'location' => $locationName,
            'centre_id' => $location->id ?? null,
            'user_id' => $userId,
            'created_by' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Log an admin-initiated data export. Called by exporters after a
     * successful export; records the export type, row count, and any
     * applied filters so a compliance review can reconstruct "who
     * exported what."
     *
     * Filter values are truncated/summarized — raw filter payloads may
     * contain search queries or identifiers that shouldn't land in the
     * feed verbatim.
     *
     * @param  array<string, mixed>  $filters
     */
    public static function logDataExport(
        string $exportType,
        int $rowCount,
        array $filters = [],
        ?Locations $location = null
    ): Activity {
        $actorName = Auth::user()->name ?? 'System';
        $filterSummary = self::summarizeFilters($filters);

        $locationName = '';
        if ($location) {
            $locationName = ($location->city->name ?? '').'-'.($location->name ?? '');
        }

        $description = '<span class="highlight">'.$actorName.'</span> exported '
            .'<span class="highlight-orange">'.$exportType.'</span> · '
            .'<span class="highlight-green">'.number_format($rowCount).' rows</span>'
            .($filterSummary !== '' ? ' · '.$filterSummary : '');

        return Activity::create([
            'account_id' => Auth::user()->account_id ?? null,
            'action' => 'exported',
            'activity_type' => 'data_export',
            'log_tier' => 'phi_audit',
            'description' => self::sanitizeDescription($description),
            'location' => $locationName,
            'centre_id' => $location->id ?? null,
            'user_id' => Auth::id(),
            'created_by' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Log a cashflow period lock/unlock. Mirrors what already goes into
     * `cashflow_audit_logs` — written in parallel so the unified activity
     * feed reflects these critical financial events.
     */
    public static function logCashflowPeriodLocked(
        string $periodLabel,
        string $action,
        string $reason = '',
        ?Locations $location = null
    ): Activity {
        $actorName = Auth::user()->name ?? 'System';
        $locationName = '';
        if ($location) {
            $locationName = ($location->city->name ?? '').'-'.($location->name ?? '');
        }

        $verb = strtolower(trim($action)) === 'unlocked' ? 'unlocked' : 'locked';
        $description = '<span class="highlight">'.$actorName.'</span> '
            .$verb.' cashflow period '
            .'<span class="highlight-purple">'.$periodLabel.'</span>'
            .($locationName ? ' for <span class="highlight">'.$locationName.'</span>' : '')
            .($reason !== '' ? ' · reason: '.$reason : '');

        return Activity::create([
            'account_id' => Auth::user()->account_id ?? null,
            'action' => $verb,
            'activity_type' => 'cashflow_period_'.$verb,
            'log_tier' => 'phi_audit',
            'description' => self::sanitizeDescription($description),
            'location' => $locationName,
            'centre_id' => $location->id ?? null,
            'user_id' => Auth::id(),
            'created_by' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Log a discount applied to an invoice. The discount name + type +
     * value + invoice ID + patient are all captured so a revenue audit
     * can reconcile "why did this patient's invoice total change."
     */
    public static function logDiscountApplied(
        Invoices $invoice,
        Discounts $discount,
        float $appliedAmount,
        Patients $patient
    ): Activity {
        $actorName = Auth::user()->name ?? 'System';
        $patientName = $patient->name ?? 'Unknown';
        $discountName = $discount->name ?? 'Discount';
        $discountType = $discount->discount_type ?? 'flat';

        $valueLabel = $discountType === 'percentage'
            ? (string) ($discount->percentage ?? 0).'%'
            : 'Rs. '.number_format((float) ($discount->amount ?? $appliedAmount));

        $description = '<span class="highlight">'.$actorName.'</span> applied '
            .'<span class="highlight-orange">'.$discountName.'</span> · '
            .$valueLabel
            .' to <span class="highlight-orange">'.$patientName.'</span>\'s '
            .'Invoice <span class="highlight-purple">#'.$invoice->id.'</span> · '
            .'saved <span class="highlight-green">Rs. '.number_format($appliedAmount).'</span>';

        return Activity::create([
            'account_id' => Auth::user()->account_id ?? $invoice->account_id ?? null,
            'action' => 'discount_applied',
            'activity_type' => 'discount_applied',
            'log_tier' => 'phi_audit',
            'description' => self::sanitizeDescription($description),
            'patient' => $patientName,
            'patient_id' => $patient->id ?? null,
            'invoice_id' => $invoice->id ?? null,
            'amount' => $appliedAmount,
            'user_id' => Auth::id(),
            'created_by' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Log an admin-initiated SMS send. System-generated reminders (cron-
     * driven) are NOT captured here — they remain in SMSLogs. Only
     * explicit user-initiated sends from the admin panel produce rows.
     */
    public static function logSmsSent(
        string $recipientName,
        string $recipientPhone,
        string $templateName,
        ?Patients $patient = null
    ): Activity {
        $actorName = Auth::user()->name ?? 'System';
        $phoneLastFour = strlen($recipientPhone) >= 4 ? '***'.substr($recipientPhone, -4) : $recipientPhone;

        $description = '<span class="highlight">'.$actorName.'</span> sent SMS · '
            .'<span class="highlight-orange">'.$templateName.'</span>'
            .' to <span class="highlight-orange">'.$recipientName.'</span> ('.$phoneLastFour.')';

        return Activity::create([
            'account_id' => Auth::user()->account_id ?? null,
            'action' => 'sms_sent',
            'activity_type' => 'sms_sent',
            'log_tier' => 'phi_audit',
            'description' => self::sanitizeDescription($description),
            'patient' => $recipientName,
            'patient_id' => $patient?->id,
            'user_id' => Auth::id(),
            'created_by' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Log a bulk SMS broadcast. Records only the template and filter
     * summary — the actual recipient list is not stored (would be too
     * large and contains PII).
     *
     * @param  array<string, mixed>  $filters
     */
    public static function logBulkSmsSent(
        int $recipientCount,
        string $templateName,
        array $filters = []
    ): Activity {
        $actorName = Auth::user()->name ?? 'System';
        $filterSummary = self::summarizeFilters($filters);

        $description = '<span class="highlight">'.$actorName.'</span> sent bulk SMS · '
            .'<span class="highlight-orange">'.$templateName.'</span> · '
            .'<span class="highlight-green">'.number_format($recipientCount).' recipients</span>'
            .($filterSummary !== '' ? ' · '.$filterSummary : '');

        return Activity::create([
            'account_id' => Auth::user()->account_id ?? null,
            'action' => 'sms_bulk_sent',
            'activity_type' => 'sms_bulk_sent',
            'log_tier' => 'phi_audit',
            'description' => self::sanitizeDescription($description),
            'user_id' => Auth::id(),
            'created_by' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Log an admin-initiated email send. Same rationale as logSmsSent —
     * auto emails (appointment reminders etc.) are NOT captured.
     */
    public static function logEmailSent(
        string $recipientName,
        string $recipientEmail,
        string $templateName,
        ?Patients $patient = null
    ): Activity {
        $actorName = Auth::user()->name ?? 'System';
        // Truncate/mask email: "a****e@x.com" to reduce PII leakage in audit feed.
        $emailMasked = self::maskEmail($recipientEmail);

        $description = '<span class="highlight">'.$actorName.'</span> sent email · '
            .'<span class="highlight-orange">'.$templateName.'</span>'
            .' to <span class="highlight-orange">'.$recipientName.'</span> ('.$emailMasked.')';

        return Activity::create([
            'account_id' => Auth::user()->account_id ?? null,
            'action' => 'email_sent',
            'activity_type' => 'email_sent',
            'log_tier' => 'phi_audit',
            'description' => self::sanitizeDescription($description),
            'patient' => $recipientName,
            'patient_id' => $patient?->id,
            'user_id' => Auth::id(),
            'created_by' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Log a read of the audit log itself (meta-audit). HIPAA §164.312(b)
     * requires recording audit-log access, not just the underlying PHI.
     *
     * Throttled so a compliance reviewer paging through the feed doesn't
     * produce a row per page — one row per user per configurable window
     * (default: 60 minutes). $mode = 'view' or 'export'.
     *
     * @param  array<string, mixed>  $filters
     */
    public static function logAuditLogAccessed(array $filters = [], string $mode = 'view'): ?Activity
    {
        $userId = Auth::id();
        if ($userId === null) {
            return null;
        }

        $throttleMinutes = (int) config('activity_log.audit_log_read_throttle_minutes', 60);
        $since = now()->subMinutes($throttleMinutes);

        $recent = Activity::where('activity_type', 'audit_log_viewed')
            ->where('action', $mode)
            ->where('created_by', $userId)
            ->where('created_at', '>=', $since)
            ->exists();

        if ($recent) {
            return null;
        }

        $actorName = Auth::user()->name ?? 'System';
        $filterSummary = self::summarizeFilters($filters);

        $verb = $mode === 'export' ? 'exported the audit log' : 'viewed the audit log';
        $description = '<span class="highlight">'.$actorName.'</span> '.$verb
            .($filterSummary !== '' ? ' · filters: '.$filterSummary : '');

        return Activity::create([
            'account_id' => Auth::user()->account_id ?? null,
            'action' => $mode,
            'activity_type' => 'audit_log_viewed',
            'log_tier' => 'security_audit',
            'description' => self::sanitizeDescription($description),
            'user_id' => $userId,
            'created_by' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Compact filter summary for logDataExport / logBulkSmsSent. Keeps
     * the description readable when filters are nested or have long
     * values — truncates any single filter value to 40 chars.
     *
     * @param  array<string, mixed>  $filters
     */
    private static function summarizeFilters(array $filters): string
    {
        if ($filters === []) {
            return '';
        }
        $parts = [];
        foreach ($filters as $k => $v) {
            if (is_array($v)) {
                $v = implode(',', array_map('strval', $v));
            }
            $vStr = (string) $v;
            if (mb_strlen($vStr) > 40) {
                $vStr = mb_substr($vStr, 0, 37).'...';
            }
            $parts[] = $k.'='.$vStr;
            if (count($parts) >= 4) {
                break;
            }
        }

        return implode(', ', $parts);
    }

    private static function maskEmail(string $email): string
    {
        $at = strpos($email, '@');
        if ($at === false || $at < 1) {
            return '***';
        }
        $local = substr($email, 0, $at);
        $domain = substr($email, $at);
        if (strlen($local) <= 2) {
            return $local[0].'***'.$domain;
        }

        return $local[0].'***'.substr($local, -1).$domain;
    }
}
