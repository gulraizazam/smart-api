<?php

declare(strict_types=1);

namespace App\Helpers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Two-mode renderer for rows in the `activities` table:
 *
 * 1. `render(object $activity): string` — legacy HTML renderer used as
 *    fallback when a row has no stored description. Output uses inline
 *    <span class="highlight*"> tags. Called by ActivityLogService only
 *    when `$activity->description === null`.
 *
 * 2. `renderCompact(object $activity): ?string` — the HIPAA-aligned single-line
 *    compact renderer. Returns HTML of the form
 *
 *        <span class="act-tag act-tag--{color}">TAG</span> {primary} · {subject} · {context} · by {actor}
 *
 *    Built from the activity's STRUCTURED COLUMNS (action, amount,
 *    patient_id, service_id, centre_id, created_by, ...) — does NOT
 *    reparse the stored description for known types. Returns null for
 *    unknown types so the caller (ActivityLogService::formatActivities)
 *    can fall back to the stored description + actor append.
 *
 * Outputs from both methods are rendered raw via {!! !!} in the Blade
 * view; every interpolated value is escaped via e() at the leaf.
 */
final class ActivityLogRenderer
{
    // =============================================================================
    // LEGACY RENDERER (unchanged — fallback path only)
    // =============================================================================

    public static function render(object $activity): string
    {
        $patient = e($activity->patientR->name ?? $activity->patient ?? '');
        $service = e($activity->serviceR->name ?? $activity->service ?? '');
        $location = e($activity->centre->name ?? $activity->location ?? '');
        $amount = $activity->amount ?? '';
        $planId = $activity->plan_id ?? '';
        $appointmentType = e($activity->appointment_type ?? '');
        $scheduleDate = $activity->schedule_date ?? '';

        $creatorName = e(self::getCreatorName($activity));

        $dateStr = '';
        if ($scheduleDate) {
            $dateStr = date('M j, Y', strtotime((string) $scheduleDate));
        } elseif ($activity->created_at) {
            $dateStr = date('M j, Y', strtotime((string) $activity->created_at));
        }

        $type = $activity->activity_type ?? '';

        return match ($type) {
            'lead_created' => '<span class="highlight">'.$creatorName.'</span> created a lead for <span class="highlight-orange">'.$patient.'</span>'.($location ? ' in <span class="highlight">'.$location.'</span>' : '').($dateStr ? ' on '.$dateStr : ''),
            'lead_booked' => '<span class="highlight">'.$creatorName.'</span> booked a <span class="highlight-orange">'.($service ?: 'Service').'</span> Consultation for <span class="highlight-orange">'.$patient.'</span>'.($location ? ' in <span class="highlight">'.$location.'</span>' : '').($dateStr ? ' on '.$dateStr : ''),
            'lead_arrived' => '<span class="highlight">'.$creatorName.'</span> marked <span class="highlight-orange">'.$patient.'</span> as arrived'.($service ? ' for <span class="highlight-orange">'.$service.'</span>' : '').($location ? ' in <span class="highlight">'.$location.'</span>' : '').($dateStr ? ' on '.$dateStr : ''),
            'consultation_booked', 'Consultancy' => '<span class="highlight">'.$creatorName.'</span> booked <span class="highlight-orange">'.($service ?: 'Service').'</span> Consultation for <span class="highlight-orange">'.$patient.'</span>'.($location ? ' in <span class="highlight">'.$location.'</span>' : '').($dateStr ? ' on '.$dateStr : ''),
            'treatment_booked' => '<span class="highlight">'.$creatorName.'</span> booked <span class="highlight-orange">'.($service ?: 'Service').'</span> Treatment for <span class="highlight-orange">'.$patient.'</span>'.($location ? ' in <span class="highlight">'.$location.'</span>' : '').($dateStr ? ' on '.$dateStr : ''),
            'package_created' => '<span class="highlight">'.$creatorName.'</span> created Package <span class="highlight-purple">Plan Id: '.$planId.'</span>'.($amount ? ' for Rs. '.number_format((float) $amount) : '').' for <span class="highlight-orange">'.$patient.'</span>'.($location ? ' in <span class="highlight">'.$location.'</span>' : '').($dateStr ? ' on '.$dateStr : ''),
            'payment_received' => '<span class="highlight">'.$creatorName.'</span> received payment <span class="highlight-green">Rs. '.number_format((float) $amount).'</span> from <span class="highlight-orange">'.$patient.'</span>'.($planId ? ' for <span class="highlight-purple">Plan Id: '.$planId.'</span>' : '').($location ? ' in <span class="highlight">'.$location.'</span>' : '').($dateStr ? ' on '.$dateStr : ''),
            'refund_made' => '<span class="highlight">'.$creatorName.'</span> made refund <span class="highlight-green">Rs. '.number_format((float) $amount).'</span> to <span class="highlight-orange">'.$patient.'</span>'.($planId ? ' for <span class="highlight-purple">Plan Id: '.$planId.'</span>' : '').($location ? ' in <span class="highlight">'.$location.'</span>' : '').($dateStr ? ' on '.$dateStr : ''),
            'invoice_created' => '<span class="highlight">'.$creatorName.'</span> created invoice <span class="highlight-green">Rs. '.number_format((float) $amount).'</span> for <span class="highlight-orange">'.($appointmentType ?: $service ?: 'Consultation').'</span>'.($location ? ' in <span class="highlight">'.$location.'</span>' : '').($dateStr ? ' on '.$dateStr : ''),
            'appointment_updated' => '<span class="highlight">'.$creatorName.'</span> updated <span class="highlight-orange">'.($service ?: 'Service').'</span> appointment for <span class="highlight-orange">'.$patient.'</span>'.($location ? ' in <span class="highlight">'.$location.'</span>' : '').($dateStr ? ' on '.$dateStr : ''),
            'membership_assigned' => '<span class="highlight">'.$creatorName.'</span> assigned membership to <span class="highlight-orange">'.$patient.'</span>'.($dateStr ? ' on '.$dateStr : ''),
            'membership_cancelled' => '<span class="highlight">'.$creatorName.'</span> cancelled membership for <span class="highlight-orange">'.$patient.'</span>'.($dateStr ? ' on '.$dateStr : ''),
            'appointment_converted' => '<span class="highlight-orange">'.$patient.'</span>\'s <span class="highlight-orange">'.($service ?: 'Service').'</span> Consultation status changed to <span class="highlight-green">Converted</span>'.($amount ? ' with payment of <span class="highlight-green">Rs. '.number_format((float) $amount).'</span>' : '').($planId ? ' in <span class="highlight-purple">Plan #'.sprintf('%05d', (int) $planId).'</span>' : ''),
            'lead_converted' => '<span class="highlight-orange">'.($service ?: 'Service').'</span> lead status changed to <span class="highlight-green">Converted</span> against <span class="highlight-orange">'.$patient.'</span>'.($location ? ' in <span class="highlight">'.$location.'</span>' : '').($amount ? ' with payment of <span class="highlight-green">Rs. '.number_format((float) $amount).'</span>' : ''),
            'consultation_deleted', 'treatment_deleted' => '<span class="highlight">'.$creatorName.'</span> deleted <span class="highlight-orange">'.$patient.'</span>\'s <span class="highlight-orange">'.($service ?: 'Service').'</span> '.($type === 'consultation_deleted' ? 'Consultation' : 'Treatment').($location ? ' in <span class="highlight">'.$location.'</span>' : '').($dateStr ? ' scheduled on '.$dateStr : ''),
            'patient_updated' => '<span class="highlight">'.$creatorName.'</span> updated <span class="highlight-orange">'.$patient.'</span>\'s profile'.($dateStr ? ' on '.$dateStr : ''),
            'invoice_cancelled' => '<span class="highlight">'.$creatorName.'</span> cancelled invoice <span class="highlight-green">Rs. '.number_format((float) $amount).'</span> for <span class="highlight-orange">'.$patient.'</span>\'s <span class="highlight-orange">'.($service ?: $appointmentType ?: 'Consultation').'</span>'.($location ? ' in <span class="highlight">'.$location.'</span>' : '').($dateStr ? ' on '.$dateStr : ''),
            'feedback_added' => '<span class="highlight">'.$creatorName.'</span> added feedback for <span class="highlight-orange">'.($service ?: 'Service').'</span> against <span class="highlight-orange">'.$patient.'</span>'.($dateStr ? ' scheduled on <span class="highlight-purple">'.$dateStr.'</span>' : ''),
            'voucher_assigned' => '<span class="highlight">'.$creatorName.'</span> assigned voucher of <span class="highlight-green">Rs. '.number_format((float) $amount).'</span> to <span class="highlight-orange">'.$patient.'</span>',
            'voucher_updated' => '<span class="highlight">'.$creatorName.'</span> updated voucher amount to <span class="highlight-green">Rs. '.number_format((float) $amount).'</span> for <span class="highlight-orange">'.$patient.'</span>',
            'voucher_consumed' => '<span class="highlight-green">Rs. '.number_format((float) $amount).'</span> consumed from voucher against <span class="highlight-orange">'.$patient.'</span>',
            'voucher_refunded' => '<span class="highlight-green">Rs. '.number_format((float) $amount).'</span> refunded to voucher for <span class="highlight-orange">'.$patient.'</span>',
            'note_added' => '<span class="highlight">'.$creatorName.'</span> added a note for <span class="highlight-orange">'.$patient.'</span>'.($dateStr ? ' on '.$dateStr : ''),
            'note_updated' => '<span class="highlight">'.$creatorName.'</span> updated a note for <span class="highlight-orange">'.$patient.'</span>'.($dateStr ? ' on '.$dateStr : ''),
            'note_deleted' => '<span class="highlight">'.$creatorName.'</span> deleted a note for <span class="highlight-orange">'.$patient.'</span>'.($dateStr ? ' on '.$dateStr : ''),
            default => self::renderByAction($activity, $creatorName, $patient, $service, $location, $amount, $planId, $appointmentType, $dateStr),
        };
    }

    private static function renderByAction(
        object $activity,
        string $creatorName,
        string $patient,
        string $service,
        string $location,
        mixed $amount,
        mixed $planId,
        string $appointmentType,
        string $dateStr,
    ): string {
        $action = $activity->action ?? '';

        switch ($action) {
            case 'booked':
            case 'treatment_booked':
            case 'Treatment Booked':
                $activityType = $activity->activity_type == 'Consultancy' ? 'Consultation' : ($activity->activity_type ?? 'Consultation');

                return '<span class="highlight">'.$creatorName.'</span> booked <span class="highlight-orange">'.($service ?: 'Service').'</span> '.$activityType.' for <span class="highlight-orange">'.$patient.'</span>'.($location ? ' in <span class="highlight">'.$location.'</span>' : '').($dateStr ? ' on '.$dateStr : '');
            case 'received':
                if ($appointmentType == 'Plan' || $planId) {
                    return '<span class="highlight">'.$creatorName.'</span> received payment <span class="highlight-green">Rs. '.number_format((float) $amount).'</span> from <span class="highlight-orange">'.$patient.'</span> for <span class="highlight-purple">Plan Id: '.$planId.'</span>'.($location ? ' in <span class="highlight">'.$location.'</span>' : '').($dateStr ? ' on '.$dateStr : '');
                }

                return '<span class="highlight">'.$creatorName.'</span> received payment'.($amount ? ' <span class="highlight-green">Rs. '.number_format((float) $amount).'</span>' : '').' from <span class="highlight-orange">'.$patient.'</span>'.($location ? ' in <span class="highlight">'.$location.'</span>' : '').($dateStr ? ' on '.$dateStr : '');
            case 'consumed':
                $activityType = $activity->activity_type == 'Consultancy' ? 'Consultation' : ($activity->activity_type ?? '');

                return '<span class="highlight">'.$creatorName.'</span> consumed <span class="highlight-green">Rs. '.number_format((float) $amount).'</span> from <span class="highlight-orange">'.$patient.'</span> for <span class="highlight-orange">'.($service ?: $appointmentType ?: 'Service').'</span> '.$activityType.($location ? ' at <span class="highlight">'.$location.'</span>' : '').($dateStr ? ' on '.$dateStr : '');
            case 'rescheduled':
            case 'Appointment Rescheduled':
            case 'Appointment Updated':
                $activityType = $activity->activity_type == 'Consultancy' ? 'Consultation' : ($activity->activity_type ?? '');

                return '<span class="highlight">'.$creatorName.'</span> '.strtolower($action).' <span class="highlight-orange">'.($service ?: 'Service').'</span> '.$activityType.' for <span class="highlight-orange">'.$patient.'</span>'.($location ? ' in <span class="highlight">'.$location.'</span>' : '').($dateStr ? ' on '.$dateStr : '');
            case 'deleted':
                $activityType = $activity->activity_type == 'Consultancy' ? 'Consultation' : ($activity->activity_type ?? '');

                return '<span class="highlight">'.$creatorName.'</span> deleted <span class="highlight-orange">'.($service ?: 'Service').'</span> '.$activityType.' for <span class="highlight-orange">'.$patient.'</span>'.($location ? ' in <span class="highlight">'.$location.'</span>' : '').($dateStr ? ' scheduled on '.$dateStr : '');
            default:
                if ($patient) {
                    return '<span class="highlight">'.$creatorName.'</span> '.strtolower($action ?: 'activity').' for <span class="highlight-orange">'.$patient.'</span>'.($location ? ' in <span class="highlight">'.$location.'</span>' : '').($dateStr ? ' on '.$dateStr : '');
                }

                return '<span class="highlight">'.$creatorName.'</span> performed '.($action ?: 'activity');
        }
    }

    // =============================================================================
    // TAG FILTER MAP — for the activity-log report UI's multi-select tag picker.
    // Given a tag family (LEAD / PAY / VCH / ...), returns the activity_types
    // and prefixes that should match when filtering. Keeps filter UI and
    // renderer dispatch in sync.
    // =============================================================================

    /**
     * @return array{types: array<int, string>, prefixes: array<int, string>}
     */
    public static function filterSpecForTag(string $tag): array
    {
        return match ($tag) {
            'LEAD' => ['types' => ['lead_created', 'lead_booked', 'lead_arrived'], 'prefixes' => []],
            'VISIT' => ['types' => ['invoice_created'], 'prefixes' => []],
            // ^ VISIT also filters by amount=0 — applied in buildQuery
            'APT' => ['types' => ['consultation_booked', 'Consultancy', 'appointment_status_changed', 'appointment_converted'], 'prefixes' => []],
            'RESC' => ['types' => ['appointment_rescheduled'], 'prefixes' => []],
            'EDIT' => ['types' => ['appointment_updated', 'patient_updated'], 'prefixes' => []],
            'DEL' => ['types' => ['consultation_deleted', 'treatment_deleted', 'appointment_deleted', 'note_deleted'], 'prefixes' => []],
            'RCVD' => ['types' => ['payment_received'], 'prefixes' => []],
            'CONS' => ['types' => ['voucher_consumed', 'invoice_created'], 'prefixes' => []],
            'BACK' => ['types' => ['invoice_cancelled', 'voucher_refunded'], 'prefixes' => []],
            'REF' => ['types' => ['refund_made'], 'prefixes' => []],
            'ADJ' => ['types' => ['payment_deleted', 'payment_updated'], 'prefixes' => []],
            'VCH' => ['types' => ['voucher_assigned', 'voucher_updated'], 'prefixes' => []],
            'TRMT' => ['types' => ['treatment_booked', 'Treatment'], 'prefixes' => []],
            'PLAN' => ['types' => ['package_created', 'package_updated', 'packages_created', 'packages_updated', 'membership_assigned', 'membership_renewed', 'membership_cancelled'], 'prefixes' => []],
            'USER' => ['types' => ['note_added', 'note_updated', 'feedback_added'], 'prefixes' => ['appointmentimage_', 'documents_', 'medical_', 'measurement_']],
            'AUTH' => ['types' => ['login', 'logout', 'login_failed', 'account_locked', 'password_reset'], 'prefixes' => ['role_', 'permission_']],
            'ACCESS' => ['types' => ['patient_accessed'], 'prefixes' => []],
            'EXPORT' => ['types' => ['data_export'], 'prefixes' => []],
            'CFG' => ['types' => ['discount_applied'], 'prefixes' => ['services_', 'bundles_', 'packages_', 'discounts_', 'smstemplates_', 'settings_', 'globaloperatorsettings_', 'telecomprovider_', 'telecomprovidernumber_', 'dbbackups_', 'taxtreatmenttype_', 'doctors_', 'doctorhaslocations_', 'doctorhasservices_', 'resources_', 'resourcehasrota_', 'resourcetimeoff_', 'workingdayexception_', 'businessclosure_', 'cashflowsetting_']],
            'HR' => ['types' => [], 'prefixes' => ['employeedetail_', 'employeedocument_', 'leaveapplication_', 'leavebalance_', 'leavetype_', 'department_', 'designation_', 'recruitmentcandidate_', 'recruitmentinterview_']],
            'STK' => ['types' => [], 'prefixes' => ['product_', 'inventory_', 'stock_', 'transferproduct_', 'brand_', 'warehouse_']],
            'MSG' => ['types' => ['sms_sent', 'sms_bulk_sent', 'email_sent'], 'prefixes' => []],
            'CASH' => ['types' => [], 'prefixes' => ['expense_', 'cashtransfer_', 'cashpool_', 'staffadvance_', 'staffreturn_', 'vendor_', 'vendortransaction_', 'vendorrequest_', 'periodlock_', 'cashflow_period_']],
            default => ['types' => [], 'prefixes' => []],
        };
    }

    /**
     * @return array<int, string>
     */
    public static function knownTags(): array
    {
        return [
            'LEAD', 'VISIT', 'APT', 'RESC', 'EDIT', 'DEL',
            'RCVD', 'CONS', 'BACK', 'REF', 'ADJ', 'VCH',
            'TRMT', 'PLAN', 'USER', 'AUTH',
            'ACCESS', 'EXPORT', 'CFG', 'HR', 'STK', 'MSG', 'CASH',
        ];
    }

    // =============================================================================
    // COMPACT RENDERER (new HIPAA-aligned single-line format)
    // =============================================================================

    /**
     * Entry point. Returns null for types the compact renderer doesn't
     * handle — caller falls back to the stored description + appendActor.
     */
    public static function renderCompact(object $activity): ?string
    {
        $type = (string) ($activity->activity_type ?? '');
        $action = (string) ($activity->action ?? '');

        return match (true) {
            self::isLeadType($type) => self::renderLead($activity, $type),
            self::isVisit($activity, $type) => self::renderVisit($activity),
            self::isAptType($type) => self::renderApt($activity, $type),
            $type === 'appointment_rescheduled' => self::renderResc($activity),
            $type === 'appointment_updated' || $type === 'patient_updated' => self::renderEdit($activity, $type),
            // ADJ must be checked BEFORE DEL — payment_deleted ends in _deleted
            // but is an administrative adjustment, not a clinical deletion.
            self::isAdjType($type) => self::renderAdj($activity, $type),
            self::isDelType($type) => self::renderDel($activity, $type),
            $type === 'payment_received' => self::renderRcvd($activity),
            self::isConsType($type, $activity) => self::renderCons($activity, $type),
            self::isBackType($type) => self::renderBack($activity, $type),
            $type === 'refund_made' => self::renderRef($activity),
            self::isVchType($type) => self::renderVch($activity, $type),
            $type === 'treatment_booked' || $type === 'Treatment' => self::renderTrmt($activity),
            self::isPlanType($type) => self::renderPlan($activity, $type),
            self::isUserType($type) => self::renderUser($activity, $type),
            self::isAuthType($type, $action) => self::renderAuth($activity, $type, $action),
            $type === 'patient_accessed' => self::renderAccess($activity),
            $type === 'data_export' => self::renderExport($activity),
            $type === 'audit_log_viewed' => self::renderAuditLogAccess($activity),
            self::isCfgType($type) => self::renderCfg($activity, $type),
            self::isHrType($type) => self::renderHr($activity, $type),
            self::isStkType($type) => self::renderStk($activity, $type),
            self::isMsgType($type) => self::renderMsg($activity, $type),
            self::isCashType($type) => self::renderCash($activity, $type),
            default => null,
        };
    }

    // -----------------------------------------------------------------------------
    // Type-family predicates
    // -----------------------------------------------------------------------------

    private static function isLeadType(string $type): bool
    {
        return in_array($type, ['lead_created', 'lead_booked', 'lead_arrived'], true);
    }

    private static function isVisit(object $a, string $type): bool
    {
        if ($type !== 'invoice_created') {
            return false;
        }
        $amount = (float) ($a->amount ?? 0);
        if ($amount > 0) {
            return false;
        }

        // Consultation signal can appear in service name, appointment_type,
        // or the stored description (older rows sometimes have null service
        // but the description says "for X Consultation").
        $searchSpace = ($a->serviceR->name ?? '').'|'
            .($a->service ?? '').'|'
            .($a->appointment_type ?? '').'|'
            .strip_tags((string) ($a->description ?? ''));

        return stripos($searchSpace, 'Consult') !== false;
    }

    private static function isAptType(string $type): bool
    {
        return in_array($type, ['consultation_booked', 'Consultancy', 'appointment_status_changed', 'appointment_converted'], true);
    }

    private static function isDelType(string $type): bool
    {
        return in_array($type, ['consultation_deleted', 'treatment_deleted', 'appointment_deleted', 'note_deleted'], true)
            || str_ends_with($type, '_deleted');
    }

    private static function isConsType(string $type, object $a): bool
    {
        if ($type === 'voucher_consumed') {
            return true;
        }

        if ($type === 'invoice_created' && (float) ($a->amount ?? 0) > 0) {
            return true;
        }

        return false;
    }

    private static function isBackType(string $type): bool
    {
        return $type === 'invoice_cancelled' || $type === 'voucher_refunded';
    }

    private static function isAdjType(string $type): bool
    {
        return $type === 'payment_deleted' || $type === 'payment_updated';
    }

    private static function isVchType(string $type): bool
    {
        return $type === 'voucher_assigned' || $type === 'voucher_updated';
    }

    private static function isPlanType(string $type): bool
    {
        return in_array($type, ['package_created', 'package_updated', 'packages_created', 'packages_updated', 'membership_assigned', 'membership_renewed', 'membership_cancelled'], true);
    }

    private static function isUserType(string $type): bool
    {
        return in_array($type, ['note_added', 'note_updated', 'feedback_added'], true)
            || str_starts_with($type, 'appointmentimage_')
            || str_starts_with($type, 'documents_')
            || str_starts_with($type, 'medical_')
            || str_starts_with($type, 'measurement_');
    }

    private static function isAuthType(string $type, string $action): bool
    {
        return in_array($type, ['login', 'logout', 'login_failed', 'account_locked', 'password_reset'], true)
            || str_starts_with($type, 'role_')
            || str_starts_with($type, 'permission_');
    }

    private static function isCfgType(string $type): bool
    {
        $prefixes = [
            'services_', 'bundles_', 'packages_', 'discounts_',
            'smstemplates_', 'settings_', 'globaloperatorsettings_',
            'telecomprovider_', 'telecomprovidernumber_', 'dbbackups_',
            'taxtreatmenttype_', 'doctors_', 'doctorhaslocations_',
            'doctorhasservices_', 'resources_', 'resourcehasrota_',
            'resourcetimeoff_', 'workingdayexception_', 'businessclosure_',
            'cashflowsetting_', 'discount_applied',
        ];

        return self::anyPrefix($type, $prefixes);
    }

    private static function isHrType(string $type): bool
    {
        $prefixes = [
            'employeedetail_', 'employeedocument_', 'leaveapplication_',
            'leavebalance_', 'leavetype_', 'department_', 'designation_',
            'recruitmentcandidate_', 'recruitmentinterview_',
        ];

        return self::anyPrefix($type, $prefixes);
    }

    private static function isStkType(string $type): bool
    {
        $prefixes = ['product_', 'inventory_', 'stock_', 'transferproduct_', 'brand_', 'warehouse_'];

        return self::anyPrefix($type, $prefixes);
    }

    private static function isMsgType(string $type): bool
    {
        return in_array($type, ['sms_sent', 'sms_bulk_sent', 'email_sent'], true);
    }

    private static function isCashType(string $type): bool
    {
        $prefixes = [
            'expense_', 'cashtransfer_', 'cashpool_', 'staffadvance_',
            'staffreturn_', 'vendor_', 'vendortransaction_', 'vendorrequest_',
            'periodlock_', 'cashflow_period_',
        ];

        return self::anyPrefix($type, $prefixes);
    }

    private static function anyPrefix(string $type, array $prefixes): bool
    {
        return array_any($prefixes, static fn (string $p): bool => str_starts_with($type, $p));
    }

    // -----------------------------------------------------------------------------
    // Type-family dispatchers — each returns the full compact HTML line
    // -----------------------------------------------------------------------------

    private static function renderLead(object $a, string $type): string
    {
        $patient = self::fieldValue($a, 'patient');
        $service = self::shortService((string) ($a->serviceR->name ?? $a->service ?? ''));
        $centre = self::abbreviateCentre((string) ($a->centre->name ?? $a->location ?? ''));
        $actor = self::actorName($a);

        $primary = match ($type) {
            'lead_created' => 'New '.e($service).' enquiry',
            'lead_booked' => e($patient)."'s ".e($service).' lead → Booked',
            'lead_arrived' => e($patient).' walked in · '.e($service),
            default => e($service).' lead',
        };

        $segments = [$primary];

        // lead_created and lead_arrived use patient as subject in primary.
        // lead_booked already has patient. Add patient segment only for lead_created.
        if ($type === 'lead_created' && $patient !== '') {
            $segments[] = e($patient);
        }
        if ($centre !== '') {
            $segments[] = e($centre);
        }

        return self::compose('LEAD', $segments, $actor);
    }

    private static function renderVisit(object $a): string
    {
        $patient = self::fieldValue($a, 'patient');
        $service = self::shortService((string) ($a->serviceR->name ?? $a->service ?? ''));
        $centre = self::abbreviateCentre((string) ($a->centre->name ?? $a->location ?? ''));
        $actor = self::actorName($a);

        $primary = e($patient).' walked in';
        $segments = [$primary];

        if ($service !== '') {
            $segments[] = e($service);
        }
        if ($centre !== '') {
            $segments[] = e($centre);
        }

        return self::compose('VISIT', $segments, $actor);
    }

    private static function renderApt(object $a, string $type): string
    {
        $patient = self::fieldValue($a, 'patient');
        $service = self::shortService((string) ($a->serviceR->name ?? $a->service ?? ''));
        $centre = self::abbreviateCentre((string) ($a->centre->name ?? $a->location ?? ''));
        $actor = self::actorName($a);
        $scheduleDate = self::formatScheduleDate((string) ($a->schedule_date ?? ''));

        $primary = match ($type) {
            'consultation_booked', 'Consultancy' => e($patient)."'s ".e($service).' consult'.($scheduleDate !== '' ? ' · '.e($scheduleDate) : ''),
            'appointment_status_changed' => e($patient)."'s ".e($service).' · '.self::extractStatusTransition($a),
            'appointment_converted' => e($patient).' became a patient · '.e($service),
            default => e($service).' appointment',
        };

        $segments = [$primary];
        if ($centre !== '') {
            $segments[] = e($centre);
        }

        return self::compose('APT', $segments, $actor);
    }

    private static function renderResc(object $a): string
    {
        $patient = self::fieldValue($a, 'patient');
        $service = self::shortService((string) ($a->serviceR->name ?? $a->service ?? ''));
        $centre = self::abbreviateCentre((string) ($a->centre->name ?? $a->location ?? ''));
        $actor = self::actorName($a);

        $transition = self::extractDateTransition($a);

        $primary = e($patient)."'s ".e($service);
        if ($transition !== '') {
            $primary .= ' · '.e($transition);
        }

        $segments = [$primary];
        if ($centre !== '') {
            $segments[] = e($centre);
        }

        return self::compose('RESC', $segments, $actor);
    }

    private static function renderEdit(object $a, string $type): string
    {
        $patient = self::fieldValue($a, 'patient');
        $service = self::shortService((string) ($a->serviceR->name ?? $a->service ?? ''));
        $centre = self::abbreviateCentre((string) ($a->centre->name ?? $a->location ?? ''));
        $actor = self::actorName($a);

        // patient_updated → mask values (PII protection: phone, email, cnic
        // values must NEVER appear in the rendered feed).
        // appointment_updated → show values (doctor, scheduled date, status —
        // safe to display).
        $maskValues = $type === 'patient_updated';

        if ($maskValues) {
            $fields = self::extractChangedFieldNames($a, excludeScheduling: true);
            if ($fields === []) {
                return self::compose('EDIT', [e($patient).' updated'], $actor);
            }
            $suffix = match (true) {
                count($fields) === 1 => e($fields[0]).' updated',
                count($fields) <= 3 => implode(', ', array_map(static fn ($f) => e($f), $fields)).' updated',
                default => implode(', ', array_map(static fn ($f) => e($f), array_slice($fields, 0, 2))).' and '.(count($fields) - 2).' more updated',
            };
            $primary = e($patient)."'s ".$suffix;
        } else {
            $diff = self::extractFieldDiff($a, excludeScheduling: true);
            if ($diff === '') {
                return self::compose('EDIT', [e($patient).($service !== '' ? "'s ".e($service) : '').' updated'], $actor);
            }
            $primary = e($patient).($service !== '' ? "'s ".e($service) : '').' · '.e($diff);
        }

        $segments = [$primary];
        if ($centre !== '') {
            $segments[] = e($centre);
        }

        return self::compose('EDIT', $segments, $actor);
    }

    private static function renderDel(object $a, string $type): string
    {
        $patient = self::fieldValue($a, 'patient');
        $service = self::shortService((string) ($a->serviceR->name ?? $a->service ?? ''));
        $centre = self::abbreviateCentre((string) ($a->centre->name ?? $a->location ?? ''));
        $actor = self::actorName($a);
        $scheduleDate = self::formatScheduleDate((string) ($a->schedule_date ?? ''));

        $primary = e($patient)."'s ".e($service).' deleted'.($scheduleDate !== '' ? ' · scheduled '.e($scheduleDate) : '');
        $segments = [$primary];
        if ($centre !== '') {
            $segments[] = e($centre);
        }

        return self::compose('DEL', $segments, $actor);
    }

    private static function renderRcvd(object $a): string
    {
        $patient = self::fieldValue($a, 'patient');
        $centre = self::abbreviateCentre((string) ($a->centre->name ?? $a->location ?? ''));
        $actor = self::actorName($a);
        $amount = self::formatAmount($a->amount);
        $planId = $a->plan_id ?? null;

        $primary = $amount.' received from '.e($patient);
        $segments = [$primary];
        if ($planId) {
            $segments[] = 'Plan #'.(int) $planId;
        }
        if ($centre !== '') {
            $segments[] = e($centre);
        }

        return self::compose('RCVD', $segments, $actor);
    }

    private static function renderCons(object $a, string $type): string
    {
        $patient = self::fieldValue($a, 'patient');
        $service = self::shortService((string) ($a->serviceR->name ?? $a->service ?? ''));
        $centre = self::abbreviateCentre((string) ($a->centre->name ?? $a->location ?? ''));
        $actor = self::actorName($a);
        $amount = self::formatAmount($a->amount);

        $voucherName = $type === 'voucher_consumed' ? self::extractVoucherName($a) : null;

        $primary = $voucherName !== null
            ? $amount.' consumed from voucher'
            : $amount.' consumed';

        $subject = e($patient).($service !== '' ? "'s ".e($service) : '');

        $segments = [$primary, $subject];
        if ($centre !== '') {
            $segments[] = e($centre);
        }

        return self::compose('CONS', $segments, $actor);
    }

    private static function renderBack(object $a, string $type): string
    {
        $patient = self::fieldValue($a, 'patient');
        $service = self::shortService((string) ($a->serviceR->name ?? $a->service ?? ''));
        $centre = self::abbreviateCentre((string) ($a->centre->name ?? $a->location ?? ''));
        $actor = self::actorName($a);
        $amount = self::formatAmount($a->amount);

        if ($type === 'invoice_cancelled') {
            $primary = $amount.' returned to '.e($patient)."'s ledger";
            $segments = [$primary];
            if ($service !== '') {
                $segments[] = e($service).' reversed';
            }
            if ($centre !== '') {
                $segments[] = e($centre);
            }

            return self::compose('BACK', $segments, $actor);
        }

        // voucher_refunded
        $primary = $amount.' credited back to voucher';
        $subject = $patient !== '' ? e($patient) : null;
        $segments = [$primary];
        if ($subject !== null) {
            $segments[] = $subject;
        }
        if ($centre !== '') {
            $segments[] = e($centre);
        }

        return self::compose('BACK', $segments, $actor);
    }

    private static function renderRef(object $a): string
    {
        $patient = self::fieldValue($a, 'patient');
        $service = self::shortService((string) ($a->serviceR->name ?? $a->service ?? ''));
        $centre = self::abbreviateCentre((string) ($a->centre->name ?? $a->location ?? ''));
        $actor = self::actorName($a);
        $amount = self::formatAmount($a->amount);

        $primary = $amount.' refunded to '.e($patient);
        $segments = [$primary];
        if ($service !== '') {
            $segments[] = e($service);
        }
        if ($centre !== '') {
            $segments[] = e($centre);
        }

        return self::compose('REF', $segments, $actor);
    }

    private static function renderAdj(object $a, string $type): string
    {
        $patient = self::fieldValue($a, 'patient');
        $centre = self::abbreviateCentre((string) ($a->centre->name ?? $a->location ?? ''));
        $actor = self::actorName($a);
        $amount = self::formatAmount($a->amount);
        $planId = $a->plan_id ?? null;

        $primary = $type === 'payment_deleted'
            ? $amount.' payment entry deleted'
            : 'Payment adjusted'.($amount !== 'Rs. 0' ? ' · '.$amount : '');

        $segments = [$primary];
        if ($patient !== '') {
            $segments[] = e($patient);
        }
        if ($planId) {
            $segments[] = 'Plan #'.(int) $planId;
        }
        if ($centre !== '') {
            $segments[] = e($centre);
        }

        return self::compose('ADJ', $segments, $actor);
    }

    private static function renderVch(object $a, string $type): string
    {
        $patient = self::fieldValue($a, 'patient');
        $centre = self::abbreviateCentre((string) ($a->centre->name ?? $a->location ?? ''));
        $actor = self::actorName($a);
        $amount = self::formatAmount($a->amount);

        if ($type === 'voucher_assigned') {
            $voucherName = self::extractVoucherName($a) ?? 'Voucher';
            $primary = $amount.' '.e($voucherName).' issued to '.e($patient);
        } else {
            $primary = e($patient)."'s voucher updated · ".$amount;
        }

        $segments = [$primary];
        if ($centre !== '') {
            $segments[] = e($centre);
        }

        return self::compose('VCH', $segments, $actor);
    }

    private static function renderTrmt(object $a): string
    {
        $patient = self::fieldValue($a, 'patient');
        $service = self::shortService((string) ($a->serviceR->name ?? $a->service ?? ''));
        $centre = self::abbreviateCentre((string) ($a->centre->name ?? $a->location ?? ''));
        $actor = self::actorName($a);
        $scheduleDate = self::formatScheduleDate((string) ($a->schedule_date ?? ''));

        $primary = e($patient).' booked '.e($service);
        if ($scheduleDate !== '') {
            $primary .= ' · '.e($scheduleDate);
        }

        $segments = [$primary];
        if ($centre !== '') {
            $segments[] = e($centre);
        }

        return self::compose('TRMT', $segments, $actor);
    }

    private static function renderPlan(object $a, string $type): string
    {
        $patient = self::fieldValue($a, 'patient');
        $centre = self::abbreviateCentre((string) ($a->centre->name ?? $a->location ?? ''));
        $actor = self::actorName($a);
        $planId = $a->plan_id ?? null;
        $amount = self::formatAmount($a->amount);

        if ($type === 'package_created' || $type === 'packages_created') {
            $primary = 'Plan #'.((int) $planId ?: '?').' created'.($amount !== 'Rs. 0' ? ' · '.$amount.' total' : '');
        } elseif ($type === 'package_updated' || $type === 'packages_updated') {
            $primary = self::planUpdatePrimary($a, (int) $planId);
        } elseif ($type === 'membership_assigned') {
            $primary = 'Membership assigned to '.e($patient);
        } elseif ($type === 'membership_renewed') {
            $primary = 'Membership renewed for '.e($patient);
        } elseif ($type === 'membership_cancelled') {
            $primary = 'Membership cancelled · '.e($patient);
        } else {
            $primary = 'Plan event';
        }

        $segments = [$primary];
        if ($patient !== '' && ! str_contains($primary, e($patient))) {
            $segments[] = e($patient);
        }
        if ($centre !== '') {
            $segments[] = e($centre);
        }

        return self::compose('PLAN', $segments, $actor);
    }

    /**
     * Detect which field changed on a package and pick the most specific
     * wording (amount increased/reduced, date extended/shortened, etc.).
     * Reads from stored description text since the observer doesn't
     * persist a structured diff. Best-effort.
     */
    private static function planUpdatePrimary(object $a, int $planId): string
    {
        $desc = (string) ($a->description ?? '');
        $label = 'Plan #'.($planId ?: '?');

        // total_price: Rs. X → Rs. Y
        if (preg_match('/total_price.*?(\d[\d,]*)\D+(\d[\d,]*)/i', $desc, $m)) {
            $old = (float) str_replace(',', '', $m[1]);
            $new = (float) str_replace(',', '', $m[2]);
            $direction = self::directionOfChange($old, $new);
            $verb = $direction === 'increased' ? 'amount increased' : ($direction === 'reduced' ? 'amount reduced' : 'amount changed');

            return $label.' '.$verb.' · Rs. '.number_format($old).' → Rs. '.number_format($new);
        }

        // end_date
        if (preg_match('/end_date.*?(\d{4}-\d{2}-\d{2}).*?(\d{4}-\d{2}-\d{2})/i', $desc, $m)) {
            $old = CarbonImmutable::parse($m[1]);
            $new = CarbonImmutable::parse($m[2]);
            $direction = $new > $old ? 'end date extended' : 'end date shortened';

            return $label.' '.$direction.' · '.$old->format('M j').' → '.$new->format('M j');
        }

        if (preg_match('/start_date.*?(\d{4}-\d{2}-\d{2}).*?(\d{4}-\d{2}-\d{2})/i', $desc, $m)) {
            $old = CarbonImmutable::parse($m[1]);
            $new = CarbonImmutable::parse($m[2]);

            return $label.' start date changed · '.$old->format('M j').' → '.$new->format('M j');
        }

        if (preg_match('/active.*?1.*?0/i', $desc)) {
            return $label.' deactivated';
        }

        return $label.' updated';
    }

    private static function renderUser(object $a, string $type): string
    {
        $patient = self::fieldValue($a, 'patient');
        $service = self::shortService((string) ($a->serviceR->name ?? $a->service ?? ''));
        $centre = self::abbreviateCentre((string) ($a->centre->name ?? $a->location ?? ''));
        $actor = self::actorName($a);

        $primary = match (true) {
            $type === 'note_added' => 'Note added to '.e($patient)."'s record",
            $type === 'note_updated' => e($patient)."'s note updated",
            $type === 'feedback_added' => self::feedbackPrimary($a, $patient),
            str_starts_with($type, 'appointmentimage_created') => 'Photo added to '.e($patient)."'s record",
            str_starts_with($type, 'appointmentimage_deleted') => 'Photo removed from '.e($patient)."'s record",
            str_starts_with($type, 'documents_created') => 'Document added to '.e($patient)."'s record",
            str_starts_with($type, 'documents_deleted') => 'Document removed from '.e($patient)."'s record",
            str_starts_with($type, 'medical_') => 'Medical record updated for '.e($patient),
            str_starts_with($type, 'measurement_created') => 'Measurement recorded for '.e($patient),
            default => 'Activity for '.e($patient),
        };

        $segments = [$primary];
        // Feedback leads with the client + rating; the treatment trails as
        // small context. (Other USER rows carry no service.)
        if ($type === 'feedback_added' && $service !== '') {
            $segments[] = e($service);
        }
        if ($centre !== '') {
            $segments[] = e($centre);
        }

        return self::compose('USER', $segments, $actor);
    }

    /**
     * Feedback line. When the originating feedback row is eager-loaded — the
     * management dashboard does this — lead with the client and the rating
     * they gave ("Jane Doe rated their visit 8/10"). Without the relation
     * loaded (e.g. the audit-log report), fall back to the plain phrasing
     * rather than firing a per-row query.
     */
    private static function feedbackPrimary(object $a, string $patient): string
    {
        $rating = self::loadedFeedbackRating($a);

        return $rating !== null
            ? e($patient).' rated their visit '.$rating.'/10'
            : 'Feedback added for '.e($patient);
    }

    private static function loadedFeedbackRating(object $a): ?int
    {
        if (! method_exists($a, 'relationLoaded') || ! $a->relationLoaded('feedbackR')) {
            return null;
        }

        $rating = $a->getRelation('feedbackR')?->rating;

        return $rating !== null ? (int) $rating : null;
    }

    private static function renderAuth(object $a, string $type, string $action): string
    {
        $desc = (string) ($a->description ?? '');
        $actor = self::actorName($a);

        // Most auth rows have meaningful stored descriptions (ip=, guard=).
        // Render the tag + description verbatim (stripped) plus actor.
        $primary = match ($type) {
            'login' => ($actor ?? 'User').' logged in'.self::extractIpSuffix($desc),
            'logout' => ($actor ?? 'User').' logged out',
            'login_failed' => 'Failed login attempt'.self::extractIpSuffix($desc),
            'account_locked' => 'Account locked'.self::extractIpSuffix($desc).' · too many failed attempts',
            'password_reset' => ($actor ?? 'User').' reset their password',
            default => self::authRolePermissionPrimary($type, $desc, $actor),
        };

        return self::compose('AUTH', [e($primary)], null); // actor already in primary
    }

    private static function authRolePermissionPrimary(string $type, string $desc, ?string $actor): string
    {
        if (str_starts_with($type, 'role_attached')) {
            return 'Role granted · by '.($actor ?? 'system');
        }
        if (str_starts_with($type, 'role_detached')) {
            return 'Role revoked · by '.($actor ?? 'system');
        }
        if (str_starts_with($type, 'permission_attached')) {
            return 'Permission granted · by '.($actor ?? 'system');
        }
        if (str_starts_with($type, 'permission_detached')) {
            return 'Permission revoked · by '.($actor ?? 'system');
        }

        return 'Auth event';
    }

    private static function renderAccess(object $a): string
    {
        $patient = self::fieldValue($a, 'patient');
        $centre = self::abbreviateCentre((string) ($a->centre->name ?? $a->location ?? ''));
        $actor = self::actorName($a);

        $primary = ($actor ?? 'User').' viewed '.e($patient)."'s record";
        $segments = [$primary];
        if ($centre !== '') {
            $segments[] = e($centre);
        }

        return self::compose('ACCESS', $segments, null);
    }

    private static function renderExport(object $a): string
    {
        $clean = self::cleanStoredDescription((string) ($a->description ?? ''));
        $actor = self::actorName($a);
        $primary = $clean !== '' ? $clean : (($actor ?? 'User').' exported data');

        return self::compose('EXPORT', [e($primary)], null);
    }

    private static function renderAuditLogAccess(object $a): string
    {
        $action = (string) ($a->action ?? 'view');
        $actor = self::actorName($a) ?? 'User';
        $verb = $action === 'export' ? 'exported the audit log' : 'viewed the audit log';

        // Filters summary lives in stored description; clean it and show tail.
        $clean = self::cleanStoredDescription((string) ($a->description ?? ''));
        $filtersPart = '';
        if (str_contains($clean, 'filters: ')) {
            $filtersPart = ' · '.trim(substr($clean, strpos($clean, 'filters: ')));
        }

        $primary = e($actor).' '.e($verb).e($filtersPart);

        return self::compose('AUTH', [$primary], null);
    }

    private static function renderCfg(object $a, string $type): string
    {
        $clean = self::cleanStoredDescription((string) ($a->description ?? ''));
        $actor = self::actorName($a);
        $primary = $clean !== '' ? $clean : ucfirst(str_replace('_', ' ', $type));

        return self::compose('CFG', [e($primary)], $actor);
    }

    private static function renderHr(object $a, string $type): string
    {
        $clean = self::cleanStoredDescription((string) ($a->description ?? ''));
        $actor = self::actorName($a);
        $primary = $clean !== '' ? $clean : ucfirst(str_replace('_', ' ', $type));

        return self::compose('HR', [e($primary)], $actor);
    }

    private static function renderStk(object $a, string $type): string
    {
        $clean = self::cleanStoredDescription((string) ($a->description ?? ''));
        $centre = self::abbreviateCentre((string) ($a->centre->name ?? $a->location ?? ''));
        $actor = self::actorName($a);
        $primary = $clean !== '' ? $clean : ucfirst(str_replace('_', ' ', $type));

        $segments = [e($primary)];
        if ($centre !== '') {
            $segments[] = e($centre);
        }

        return self::compose('STK', $segments, $actor);
    }

    private static function renderMsg(object $a, string $type): string
    {
        $clean = self::cleanStoredDescription((string) ($a->description ?? ''));
        $actor = self::actorName($a);
        $primary = $clean !== '' ? $clean : ucfirst(str_replace('_', ' ', $type));

        // Stored description already starts with actor name for SMS/email
        // writes (e.g. "Alice sent SMS · Reminder ..."). Skip the "· by X"
        // suffix to avoid duplicating the actor.
        $appendActor = $actor === null || ! str_contains($primary, $actor);

        return self::compose('MSG', [e($primary)], $appendActor ? $actor : null);
    }

    private static function renderCash(object $a, string $type): string
    {
        $clean = self::cleanStoredDescription((string) ($a->description ?? ''));
        $centre = self::abbreviateCentre((string) ($a->centre->name ?? $a->location ?? ''));
        $actor = self::actorName($a);
        $amount = self::formatAmount($a->amount);

        $primary = $clean !== '' ? $clean : ucfirst(str_replace('_', ' ', $type));
        if ($amount !== 'Rs. 0' && ! str_contains($primary, 'Rs.')) {
            $primary .= ' · '.$amount;
        }

        $segments = [e($primary)];
        if ($centre !== '') {
            $segments[] = e($centre);
        }

        // Skip actor-append if the stored description already contains it
        // (cashflow/expense writers produce "Alice locked cashflow period ...").
        $appendActor = $actor === null || ! str_contains($primary, $actor);

        return self::compose('CASH', $segments, $appendActor ? $actor : null);
    }

    /**
     * Normalize a stored description for safe reuse inside the compact
     * renderer. The description may already contain sanitized HTML spans
     * from ActivityLogger::sanitizeDescription, OR literal escaped entities
     * (when the sanitizer escaped a non-whitelisted tag). Strip tags first,
     * then decode any remaining entities so the result is clean UTF-8 that
     * the renderer can safely wrap in its own markup via e().
     */
    private static function cleanStoredDescription(string $raw): string
    {
        if ($raw === '') {
            return '';
        }

        // Remove any whitelisted spans from the sanitized description.
        $stripped = strip_tags($raw);
        // Decode `&lt;span ...&gt;` sequences (non-whitelisted tags that the
        // sanitizer escaped into text). Also handles `&amp;`, `&quot;`, etc.
        $decoded = html_entity_decode($stripped, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Now strip any second-layer tags that the decoder just revealed.
        $final = strip_tags($decoded);

        return trim((string) $final);
    }

    // -----------------------------------------------------------------------------
    // Composition + helpers
    // -----------------------------------------------------------------------------

    /**
     * Compose the final HTML: colored tag pill + middot-separated segments + actor suffix.
     *
     * @param  array<int, string>  $segments  pre-escaped text segments
     */
    private static function compose(string $tag, array $segments, ?string $actor): string
    {
        $color = (string) (config('activity_log.tag_colors.'.$tag) ?? 'zinc');
        $pill = '<span class="act-tag act-tag--'.e($color).'">'.e($tag).'</span>';

        $body = implode(' · ', array_filter($segments, static fn ($s): bool => (string) $s !== ''));

        if ($actor !== null && $actor !== '') {
            $body .= ' · <span class="act-actor">by '.e($actor).'</span>';
        }

        return $pill.' '.$body;
    }

    private static function fieldValue(object $a, string $field): string
    {
        $value = $a->{$field} ?? '';
        if (is_string($value) && $value !== '') {
            return trim($value);
        }

        // Fallback: patient relation
        if ($field === 'patient') {
            return (string) ($a->patientR->name ?? '');
        }

        return '';
    }

    private static function abbreviateCentre(string $name): string
    {
        if ($name === '') {
            return '';
        }
        $map = (array) config('activity_log.centre_labels', []);

        return (string) ($map[$name] ?? $name);
    }

    private static function shortService(string $name): string
    {
        if ($name === '') {
            return '';
        }
        $map = (array) config('activity_log.service_shortnames', []);
        if (isset($map[$name])) {
            return (string) $map[$name];
        }

        // Generic suffix trimming
        $out = preg_replace('/\s+Treatment$/i', '', $name) ?? $name;
        $out = preg_replace('/\s+Consultation$/i', ' consult', $out) ?? $out;

        return (string) $out;
    }

    private static function formatAmount(mixed $amount): string
    {
        if ($amount === null || $amount === '') {
            return 'Rs. 0';
        }

        $num = is_numeric($amount) ? (float) $amount : (float) str_replace(',', '', (string) $amount);

        return 'Rs. '.number_format($num);
    }

    private static function formatScheduleDate(string $date): string
    {
        if ($date === '') {
            return '';
        }
        try {
            $d = CarbonImmutable::parse($date);

            return $d->year === CarbonImmutable::now()->year
                ? $d->format('M j')
                : $d->format('M j, Y');
        } catch (Throwable) {
            return '';
        }
    }

    private static function actorName(object $a): ?string
    {
        $name = $a->user->name ?? null;
        if (is_string($name) && $name !== '') {
            return $name;
        }

        $createdBy = $a->created_by ?? null;
        if ($createdBy && ! is_numeric($createdBy)) {
            return (string) $createdBy;
        }

        return null;
    }

    private static function directionOfChange(float $old, float $new): string
    {
        return match (true) {
            $new > $old => 'increased',
            $new < $old => 'reduced',
            default => 'unchanged',
        };
    }

    private static function extractIpSuffix(string $desc): string
    {
        if (preg_match('/ip=([\d.]+)/', $desc, $m)) {
            return ' · from '.$m[1];
        }

        return '';
    }

    private static function extractVoucherName(object $a): ?string
    {
        $desc = (string) ($a->description ?? '');
        // ActivityLogger::logVoucherAssigned format: "... assigned X voucher of Rs. ..."
        if (preg_match('/assigned\s+(?:<[^>]+>)?([^<]+?)(?:<[^>]+>)?\s+voucher/i', $desc, $m)) {
            return trim($m[1]);
        }
        // ActivityLogger::logVoucherConsumed format: "... voucher NAME ..."
        if (preg_match('/voucher\s+(?:<[^>]+>)?([^<\s]+)/i', $desc, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    /**
     * Parse "status from X to Y" or "X → Y" from the stored description.
     */
    private static function extractStatusTransition(object $a): string
    {
        $desc = strip_tags((string) ($a->description ?? ''));
        if (preg_match('/status from (\w+) to (\w+)/i', $desc, $m)) {
            return $m[1].' → '.$m[2];
        }
        if (preg_match('/(\w+)\s*→\s*(\w+)/', $desc, $m)) {
            return $m[1].' → '.$m[2];
        }

        return 'status changed';
    }

    private static function extractDateTransition(object $a): string
    {
        $desc = strip_tags((string) ($a->description ?? ''));
        // "from Apr 1, 2026 12:00 PM to Mar 31, 2026 05:00 PM"
        if (preg_match('/from\s+(.+?\d{4}(?:\s+\d{1,2}:\d{2}\s+[AP]M)?)\s+to\s+(.+?\d{4}(?:\s+\d{1,2}:\d{2}\s+[AP]M)?)/i', $desc, $m)) {
            return self::compactDateLabel($m[1]).' → '.self::compactDateLabel($m[2]);
        }

        return '';
    }

    private static function compactDateLabel(string $raw): string
    {
        try {
            $d = CarbonImmutable::parse(trim($raw));
            $time = $d->format('g:ia');

            return $d->format('M j').' '.strtolower($time);
        } catch (Throwable) {
            return trim($raw);
        }
    }

    /**
     * Parse changed fields from the stored description of an
     * appointment_updated / patient_updated row. Each tuple: [field, old, new].
     * Regex stops the "new" capture before " in " (location suffix) or a
     * following comma-separated field name, so location names and
     * trailing clauses don't leak into the diff.
     *
     * @return array<int, array{0: string, 1: string, 2: string}>
     */
    private static function parseFieldTuples(object $a, bool $excludeScheduling): array
    {
        $desc = strip_tags((string) ($a->description ?? ''));
        // Cut off trailing " in <Location>" or " on <Date>" clauses — those are
        // context, not part of the diff.
        $desc = preg_replace('/\s+(?:in|at|on)\s+[A-Z].*$/', '', $desc) ?? $desc;

        preg_match_all('/([A-Za-z][A-Za-z _]+?):\s*([^,→\n]+?)\s*→\s*([^,\n]+?)(?=,\s*[A-Z][A-Za-z ]+:|$)/u', $desc, $matches, PREG_SET_ORDER);

        $tuples = [];
        foreach ($matches as $m) {
            $field = trim($m[1]);
            $old = trim($m[2]);
            $new = trim($m[3]);
            if ($old === $new) {
                continue;
            }
            if ($excludeScheduling && preg_match('/^(Scheduled\s+(Date|Time)|Schedule\s+(Date|Time))$/i', $field)) {
                continue;
            }
            $tuples[] = [$field, $old, $new];
        }

        return $tuples;
    }

    private static function extractFieldDiff(object $a, bool $excludeScheduling): string
    {
        $tuples = self::parseFieldTuples($a, $excludeScheduling);
        $parts = [];
        foreach (array_slice($tuples, 0, 3) as [$f, $o, $n]) {
            $parts[] = $f.': '.$o.' → '.$n;
        }

        return implode(', ', $parts);
    }

    /**
     * @return array<int, string>
     */
    private static function extractChangedFieldNames(object $a, bool $excludeScheduling): array
    {
        $tuples = self::parseFieldTuples($a, $excludeScheduling);

        return array_values(array_unique(array_map(static fn (array $t): string => $t[0], $tuples)));
    }

    private static function getCreatorName(object $activity): string
    {
        if (isset($activity->user) && $activity->user && $activity->user->name) {
            return $activity->user->name;
        }

        $createdBy = $activity->created_by ?? null;

        if ($createdBy && ! is_numeric($createdBy)) {
            return (string) $createdBy;
        }

        if (is_numeric($createdBy) && (int) $createdBy > 0) {
            $user = DB::table('users')->where('id', (int) $createdBy)->first();
            if ($user) {
                return $user->name;
            }
        }

        return 'System';
    }
}
