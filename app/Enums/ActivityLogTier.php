<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Retention tier for rows in the `activities` table.
 *
 * Each tier has its own retention window defined in
 * config/activity_retention.php and is applied by the
 * `activities:archive` scheduled command.
 *
 * The active tier set is HIPAA-aligned:
 *   - PhiAudit         — all PHI-touching events (patient, appointment, treatment,
 *                         payment, plan, catalog, cashflow). 6-year minimum; archive
 *                         after 540 days; never delete.
 *   - SecurityAudit    — auth and privilege events (login, role, permission,
 *                         password reset). 6-year minimum; archive after 540
 *                         days; never delete.
 *   - HrStandard       — employee records, leaves, departments. Governed by
 *                         labor law (not HIPAA). 365d hot, 365d cold, hard-
 *                         delete at 2 years.
 *   - HrCandidatesRejected — non-hired candidate PII. Minimization: 6 months
 *                             then hard-delete.
 *
 * Cases Clinical / Financial / Operational / Security are retained only for
 * backwards compatibility with rows written before the HIPAA reclassification
 * command runs. `classify()` only returns the new cases. The transitional
 * tiers in config/activity_retention.php allow the archive command to
 * process pre-reclassification rows correctly; once a database-wide
 * reclassification has completed, the deprecated cases can be removed.
 *
 * Rows with an unmapped `activity_type` AND `action` are left with NULL
 * tier and are NEVER pruned — a human must classify them first.
 */
enum ActivityLogTier: string
{
    case PhiAudit = 'phi_audit';
    case SecurityAudit = 'security_audit';
    case HrStandard = 'hr_standard';
    case HrCandidatesRejected = 'hr_candidates_rejected';

    /** @deprecated — reclassify existing rows to PhiAudit; remove after DB migration */
    case Clinical = 'clinical';
    /** @deprecated — reclassify existing rows to PhiAudit */
    case Financial = 'financial';
    /** @deprecated — reclassify existing rows to PhiAudit */
    case Operational = 'operational';
    /** @deprecated — reclassify existing rows to SecurityAudit */
    case Security = 'security';

    public function label(): string
    {
        return match ($this) {
            self::PhiAudit => 'PHI Audit',
            self::SecurityAudit => 'Security Audit',
            self::HrStandard => 'HR Standard',
            self::HrCandidatesRejected => 'HR Candidates (Rejected)',
            self::Clinical => 'Clinical (legacy)',
            self::Financial => 'Financial (legacy)',
            self::Operational => 'Operational (legacy)',
            self::Security => 'Security (legacy)',
        };
    }

    /**
     * Classify a row into a retention tier.
     *
     * Inspects `activity_type` first; falls back to `action` for legacy rows
     * written before activity_type existed. Returns null when neither signal
     * maps to a known tier — the caller MUST treat null as "do not prune."
     *
     * Under HIPAA alignment, most event types collapse to PhiAudit because
     * anything touching patient records is PHI and requires 6-year retention.
     * HR (employee) events get HrStandard. Rejected recruitment candidates
     * get HrCandidatesRejected for PII minimization. Auth/role events get
     * SecurityAudit.
     */
    public static function classify(?string $activityType, ?string $action = null): ?self
    {
        if ($activityType !== null && $activityType !== '') {
            $tier = self::classifyType($activityType);
            if ($tier !== null) {
                return $tier;
            }
        }

        if ($action !== null && $action !== '') {
            return self::classifyAction($action);
        }

        return null;
    }

    private static function classifyType(string $activityType): ?self
    {
        // Title-case legacy values written by older code paths.
        $titleCase = match ($activityType) {
            'Consultancy',
            'Treatment',
            'Appointment',
            'Patient',
            'Feedback',
            'Invoice',
            'Payment',
            'Voucher',
            'Membership',
            'Package',
            'Refund',
            'Lead' => self::PhiAudit,
            default => null,
        };

        if ($titleCase !== null) {
            return $titleCase;
        }

        return self::classifyByPrefix(strtolower($activityType));
    }

    private static function classifyByPrefix(string $lower): ?self
    {
        $startsWithAny = static fn (array $prefixes): bool => array_any(
            $prefixes,
            static fn (string $p): bool => str_starts_with($lower, $p),
        );

        // Rejected recruitment candidates — 6-month retention (PII minimization).
        // We can't detect "rejected" from activity_type alone (it's a status
        // stored in the row's description or metadata). Conservative default:
        // recruitmentcandidate_* → HrStandard; specialized reclassification
        // command demotes rejected ones to HrCandidatesRejected.
        if ($startsWithAny(['recruitmentcandidate_', 'recruitmentinterview_'])) {
            return self::HrStandard;
        }

        // HR events — 2-year retention
        if ($startsWithAny([
            'employeedetail_',
            'employeedocument_',
            'leaveapplication_',
            'leavebalance_',
            'leavetype_',
            'department_',
            'designation_',
            'hrnotification_',
        ])) {
            return self::HrStandard;
        }

        // Security / auth events — 6 years
        if ($startsWithAny([
            'login',
            'logout',
            'auth_',
            'password_',
            '2fa_',
            'account_locked',
            'role_',
            'permission_',
        ])) {
            return self::SecurityAudit;
        }

        // Everything else that touches PHI or financial records → 6 years.
        // This is the wide net: leads, patients, appointments, consultations,
        // treatments, notes, feedback, payments, vouchers, invoices, refunds,
        // memberships, packages, plans, services, bundles, discounts, inventory,
        // cashflow, catalog, system settings, doctor credentialing, resource
        // scheduling, clinical records, exports, access logs — all PhiAudit.
        if ($startsWithAny([
            'lead_',
            'appointment_',
            'consultation_',
            'treatment_',
            'patient_',
            'note_',
            'feedback_',
            'payment_',
            'invoice_',
            'voucher_',
            'refund_',
            'membership_',
            'package_',
            'services_',
            'service_',
            'bundles_',
            'bundle_',
            'packages_',
            'discounts_',
            'discount_',
            'product_',
            'inventory_',
            'stock_',
            'transferproduct_',
            'brand_',
            'warehouse_',
            'expense_',
            'cashtransfer_',
            'cashpool_',
            'staffadvance_',
            'staffreturn_',
            'vendor_',
            'vendortransaction_',
            'vendorrequest_',
            'periodlock_',
            'cashflow_',
            'doctor_',
            'doctors_',
            'doctorhaslocations_',
            'doctorhasservices_',
            'appointmentimage_',
            'medical_',
            'measurement_',
            'documents_',
            'settings_',
            'globaloperatorsettings_',
            'telecomprovider_',
            'dbbackups_',
            'taxtreatmenttype_',
            'resources_',
            'resourcehasrota_',
            'resourcetimeoff_',
            'workingdayexception_',
            'businessclosure_',
            'order_',
            'orderdetail_',
            'orderrefunddetail_',
            'smstemplates_',
            'sms_',
            'email_',
            'patient_accessed',
            'data_export',
            'discount_applied',
        ])) {
            return self::PhiAudit;
        }

        return null;
    }

    private static function classifyAction(string $action): ?self
    {
        // Legacy rows where activity_type is NULL but action is set.
        // Narrow match — verbs unambiguous to financial/PHI flow.
        return match (strtolower(trim($action))) {
            'received',
            'consumed',
            'refunded',
            'paid',
            'invoiced' => self::PhiAudit,
            default => null,
        };
    }
}
