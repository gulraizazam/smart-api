<?php

declare(strict_types=1);

use App\Enums\ActivityLogTier;
use App\Models\Activity;
use App\Models\Appointmentimage;
use App\Models\AppointmentLog;
use App\Models\Appointments;
use App\Models\AppointmentsDailyStats;
use App\Models\AuditTrailActions;
use App\Models\AuditTrailChanges;
use App\Models\AuditTrails;
use App\Models\AuditTrailTables;
use App\Models\BaseModel;
use App\Models\Brand;
use App\Models\Bundles;
use App\Models\BundleServicesPriceHistory;
use App\Models\BusinessClosure;
use App\Models\CashFlow\CashflowAuditLog;
use App\Models\CashFlow\CashflowSetting;
use App\Models\CashFlow\CashPool;
use App\Models\CashFlow\CashTransfer;
use App\Models\CashFlow\Expense;
use App\Models\CashFlow\ExpenseCategory;
use App\Models\CashFlow\PeriodLock;
use App\Models\CashFlow\StaffAdvance;
use App\Models\CashFlow\StaffReturn;
use App\Models\CashFlow\Vendor;
use App\Models\CashFlow\VendorRequest;
use App\Models\CashFlow\VendorTransaction;
use App\Models\Consultancy;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Discounts;
use App\Models\Doctors;
use App\Models\Documents;
use App\Models\EmployeeDetail;
use App\Models\EmployeeDocument;
use App\Models\GlobalOperatorSettings;
use App\Models\Inventory;
use App\Models\InventoryLog;
use App\Models\InventoryLogAction;
use App\Models\InventoryLogTable;
use App\Models\Invoices;
use App\Models\Leads;
use App\Models\LeaveApplication;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\Measurement;
use App\Models\Medical;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Packages;
use App\Models\Patients;
use App\Models\Product;
use App\Models\RecruitmentCandidate;
use App\Models\RecruitmentInterview;
use App\Models\Resources;
use App\Models\Services;
use App\Models\Settings;
use App\Models\SMSLogs;
use App\Models\SMSTemplates;
use App\Models\Stock;
use App\Models\Telecomprovider;
use App\Models\Telecomprovidernumber;
use App\Models\TransferProduct;
use App\Models\Treatment;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkingDayException;
use Illuminate\Notifications\DatabaseNotification;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Behavior of App\Observers\ActivityLogObserver and
 * App\Providers\AppServiceProvider::registerObservedModels(), plus the
 * rendering catalog consumed by App\Helpers\ActivityLogRenderer.
 *
 * Tier vocabulary is HIPAA-aligned — see config/activity_retention.php
 * and App\Enums\ActivityLogTier for the retention durations.
 */
return [

    'enabled' => env('ACTIVITY_LOG_OBSERVER_ENABLED', true),

    'auto_discover' => env('ACTIVITY_LOG_AUTO_DISCOVER', true),

    /*
     * HIPAA-aligned fallback tier for auto-discovered models without an
     * explicit entry in `tiers`. Default: phi_audit (6-year retention,
     * append-only). Safer than a short default: under HIPAA "when in doubt,
     * retain." Storage cost of keeping generic lookup-table churn for 6
     * years is tiny compared to the cost of forgotten-PHI short-retention.
     */
    'default_tier' => env('ACTIVITY_LOG_DEFAULT_TIER', ActivityLogTier::PhiAudit->value),

    /*
     * Throttle window for patient-access log writes (logPatientAccessed).
     * One row per user/patient/day keeps the audit feed usable even when
     * a clinician opens a file multiple times during a consultation.
     */
    'access_throttle_minutes' => (int) env('ACTIVITY_ACCESS_THROTTLE_MINUTES', 1440),

    /*
     * Throttle window for audit-log READ logging (logAuditLogAccessed): one row
     * per user per window keeps "who viewed the audit log" from flooding the
     * feed. Read via config() (not env() directly) so config:cache stays safe.
     */
    'audit_log_read_throttle_minutes' => (int) env('ACTIVITY_AUDIT_LOG_READ_THROTTLE_MINUTES', 60),

    /*
     * Per-model tier overrides. FQCN => ActivityLogTier enum value.
     */
    'tiers' => [

        // Core PHI-bearing models — 6-year retention (phi_audit)
        Patients::class => ActivityLogTier::PhiAudit->value,
        Appointments::class => ActivityLogTier::PhiAudit->value,
        Consultancy::class => ActivityLogTier::PhiAudit->value,
        Treatment::class => ActivityLogTier::PhiAudit->value,
        Invoices::class => ActivityLogTier::PhiAudit->value,
        Leads::class => ActivityLogTier::PhiAudit->value,
        Appointmentimage::class => ActivityLogTier::PhiAudit->value,
        Medical::class => ActivityLogTier::PhiAudit->value,
        Measurement::class => ActivityLogTier::PhiAudit->value,
        Documents::class => ActivityLogTier::PhiAudit->value,

        // Catalog — prices and definitions. Financial/PHI-adjacent.
        Services::class => ActivityLogTier::PhiAudit->value,
        Bundles::class => ActivityLogTier::PhiAudit->value,
        Packages::class => ActivityLogTier::PhiAudit->value,
        Discounts::class => ActivityLogTier::PhiAudit->value,

        // Inventory — movements affect clinical supply
        Product::class => ActivityLogTier::PhiAudit->value,
        Inventory::class => ActivityLogTier::PhiAudit->value,
        Stock::class => ActivityLogTier::PhiAudit->value,
        TransferProduct::class => ActivityLogTier::PhiAudit->value,
        Brand::class => ActivityLogTier::PhiAudit->value,
        Warehouse::class => ActivityLogTier::PhiAudit->value,

        // CashFlow module — money movement, long retention
        Expense::class => ActivityLogTier::PhiAudit->value,
        ExpenseCategory::class => ActivityLogTier::PhiAudit->value,
        CashTransfer::class => ActivityLogTier::PhiAudit->value,
        CashPool::class => ActivityLogTier::PhiAudit->value,
        StaffAdvance::class => ActivityLogTier::PhiAudit->value,
        StaffReturn::class => ActivityLogTier::PhiAudit->value,
        Vendor::class => ActivityLogTier::PhiAudit->value,
        VendorRequest::class => ActivityLogTier::PhiAudit->value,
        VendorTransaction::class => ActivityLogTier::PhiAudit->value,
        PeriodLock::class => ActivityLogTier::PhiAudit->value,
        CashflowSetting::class => ActivityLogTier::PhiAudit->value,

        // Orders — retail sales
        Order::class => ActivityLogTier::PhiAudit->value,
        OrderDetail::class => ActivityLogTier::PhiAudit->value,

        // Resource scheduling — affects patient scheduling
        Resources::class => ActivityLogTier::PhiAudit->value,
        BusinessClosure::class => ActivityLogTier::PhiAudit->value,
        WorkingDayException::class => ActivityLogTier::PhiAudit->value,

        // Doctor credentialing — who treats whom/where. Security-critical.
        Doctors::class => ActivityLogTier::SecurityAudit->value,

        // System settings — config changes auditable forever
        Settings::class => ActivityLogTier::SecurityAudit->value,
        GlobalOperatorSettings::class => ActivityLogTier::SecurityAudit->value,
        Telecomprovider::class => ActivityLogTier::SecurityAudit->value,
        Telecomprovidernumber::class => ActivityLogTier::SecurityAudit->value,
        SMSTemplates::class => ActivityLogTier::SecurityAudit->value,

        // Auth / user identity
        User::class => ActivityLogTier::SecurityAudit->value,

        // HR — employment records, labor-law-relevant, 2-year retention
        EmployeeDetail::class => ActivityLogTier::HrStandard->value,
        EmployeeDocument::class => ActivityLogTier::HrStandard->value,
        LeaveApplication::class => ActivityLogTier::HrStandard->value,
        LeaveBalance::class => ActivityLogTier::HrStandard->value,
        LeaveType::class => ActivityLogTier::HrStandard->value,
        Department::class => ActivityLogTier::HrStandard->value,
        Designation::class => ActivityLogTier::HrStandard->value,

        // Recruitment candidates — default HR. HrCandidatesRejected (6-month
        // PII minimization) must be assigned explicitly by a reclassification
        // rule once a candidate's status is marked "rejected/not hired."
        RecruitmentCandidate::class => ActivityLogTier::HrStandard->value,
        RecruitmentInterview::class => ActivityLogTier::HrStandard->value,
    ],

    /*
     * Per-model attribute whitelist.
     */
    'whitelists' => [

        // User identity + privilege
        User::class => [
            'name', 'email', 'phone', 'cnic',
            'main_account', 'user_type_id', 'resource_type_id',
            'account_id', 'active', 'hr_managed', 'is_admin',
            'commission', 'can_perform_consultation',
        ],

        // Catalog
        Services::class => ['name', 'price', 'active', 'parent_id', 'sort_number'],
        Bundles::class => ['name', 'price', 'active', 'sort_number'],
        Packages::class => ['name', 'total_price', 'start_date', 'end_date', 'active', 'status', 'patient_id'],
        Discounts::class => ['name', 'discount_type', 'amount', 'percentage', 'valid_from', 'valid_to', 'active'],

        // Inventory
        Product::class => ['name', 'brand_id', 'price', 'active'],
        Stock::class => ['product_id', 'location_id', 'qty', 'min_qty'],
        TransferProduct::class => ['product_id', 'from_location_id', 'to_location_id', 'qty', 'status'],

        // HR
        EmployeeDetail::class => [
            'user_id', 'department_id', 'designation_id', 'employment_type',
            'join_date', 'exit_date', 'active', 'salary',
        ],
        LeaveApplication::class => [
            'user_id', 'leave_type_id', 'start_date', 'end_date', 'status', 'approved_by',
        ],
        LeaveBalance::class => ['user_id', 'leave_type_id', 'total_days', 'used_days'],
        RecruitmentCandidate::class => ['name', 'position', 'status'],
        RecruitmentInterview::class => ['candidate_id', 'interview_date', 'verdict', 'interviewer_id'],

        // CashFlow
        Expense::class => ['expense_category_id', 'amount', 'location_id', 'status', 'paid_at'],
        CashTransfer::class => ['amount', 'from_location_id', 'to_location_id', 'status'],
        StaffAdvance::class => ['user_id', 'amount', 'status'],
        StaffReturn::class => ['user_id', 'amount', 'status'],
        VendorTransaction::class => ['vendor_id', 'amount', 'transaction_type'],
        PeriodLock::class => ['period', 'location_id', 'locked_at', 'unlocked_at', 'status'],

        // Doctor credentialing
        Doctors::class => ['user_id', 'license_number', 'specialization', 'active'],
    ],

    /*
     * Value-masking override. Default is true (show only field names changed).
     * Set false for models where before/after VALUES are the audit interest
     * (catalog prices, money amounts, role/status enums — not PII).
     */
    'mask_values' => [
        // Catalog — prices and names ARE the audit information
        Services::class => false,
        Bundles::class => false,
        Packages::class => false,
        Discounts::class => false,

        // Inventory
        Product::class => false,
        Stock::class => false,
        TransferProduct::class => false,

        // CashFlow — amounts are the point
        Expense::class => false,
        CashTransfer::class => false,
        StaffAdvance::class => false,
        StaffReturn::class => false,
        VendorTransaction::class => false,
        PeriodLock::class => false,

        // HR status transitions are safe (no PII)
        LeaveApplication::class => false,
        RecruitmentCandidate::class => false,
        RecruitmentInterview::class => false,
    ],

    /*
     * Activity types to hide from the rendered feed (not deleted from DB).
     * Use when an event fires as a pair with another more informative row —
     * suppressing the duplicate keeps the feed readable.
     */
    'suppress_from_display' => [
        'lead_converted', // fires as a pair with appointment_converted — keep the latter
    ],

    /*
     * Models NEVER attached by the auto-observer.
     */
    'exclude' => [
        // Self-reference — would cause infinite recursion
        Activity::class,

        // Legacy audit system
        AuditTrails::class,
        AuditTrailChanges::class,
        AuditTrailActions::class,
        AuditTrailTables::class,

        // Rollup / derived tables
        AppointmentsDailyStats::class,
        AppointmentLog::class,
        BundleServicesPriceHistory::class,

        // Module-specific audit/log tables — avoid audit-of-audit
        CashflowAuditLog::class,
        InventoryLog::class,
        InventoryLogAction::class,
        InventoryLogTable::class,

        // High-volume delivery records — captured by explicit writers
        // when admin-initiated (see ActivityLogger::logSmsSent).
        SMSLogs::class,

        // Internal plumbing
        BaseModel::class,
        PersonalAccessToken::class,
        DatabaseNotification::class,
    ],

    /*
     * Global denylist. Applied after per-model whitelist — a model
     * whitelisting a secret by mistake still gets it scrubbed here.
     */
    'denylist' => [
        'password',
        'password_confirmation',
        'current_password',
        'remember_token',
        'api_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        '2fa_secret',
        'session_id',
        'totp_secret',
        'locked_until',
        'failed_login_attempts',
        'failed_login_count',
        'last_failed_login_at',
        'last_login_at',

        'created_at',
        'updated_at',
        'deleted_at',
    ],

    /*
     * Centre abbreviations for the compact renderer. Long centre names
     * from `locations.name` (or the city-prefixed form "Karachi-CUTERA
     * Johar Karachi") map to a ~5-char label. Unmapped centres fall
     * through to the raw name (truncated to 15 chars by the renderer).
     */
    'centre_labels' => [
        'Hyderabad-CUTERA Hyderabad' => 'Hyd',
        'CUTERA Hyderabad' => 'Hyd',
        'CUTERA Saddar Rawalpindi' => 'Rwp Saddar',
        'Rawalpindi-CUTERA Saddar Rawalpindi' => 'Rwp Saddar',
        'Faisalabad-CUTERA Faisalabad' => 'Fsd',
        'CUTERA Faisalabad' => 'Fsd',
        'Karachi-CUTERA Johar Karachi' => 'Khi Johar',
        'CUTERA Johar Karachi' => 'Khi Johar',
        'Karachi-CUTERA DHA Karachi' => 'Khi DHA',
        'CUTERA DHA Karachi' => 'Khi DHA',
        'Karachi-CUTERA Bahadurabad Karachi' => 'Khi Bhd',
        'CUTERA Bahadurabad Karachi' => 'Khi Bhd',
        'Islamabad-CUTERA F-7 Islamabad' => 'Isb F-7',
        'CUTERA F-7 Islamabad' => 'Isb F-7',
        'Islamabad-CUTERA I-8 Islamabad' => 'Isb I-8',
        'CUTERA I-8 Islamabad' => 'Isb I-8',
        'Lahore-CUTERA Gulberg Lahore' => 'Lhr Gbg',
        'CUTERA Gulberg Lahore' => 'Lhr Gbg',
        'Lahore-CUTERA DHA Lahore' => 'Lhr DHA',
        'CUTERA DHA Lahore' => 'Lhr DHA',
        'Lahore-CUTERA Johar Town Lahore' => 'Lhr JT',
        'CUTERA Johar Town Lahore' => 'Lhr JT',
        'Sialkot-CUTERA Sialkot' => 'Skt',
        'CUTERA Sialkot' => 'Skt',
    ],

    /*
     * Service-name short forms used ONLY in service *references* (e.g. in
     * [TRMT], [RESC], [CONS] rows describing a service being used).
     * Catalog entity audit rows (services_created / services_updated)
     * render the EXACT stored name — do NOT pass catalog names through
     * this map. The renderer's shortService() applies only to non-catalog
     * contexts.
     */
    'service_shortnames' => [
        'CoolGlide - Face incl. Front of Neck Treatment' => 'CoolGlide Face+Neck',
        'CoolGlide - Female Full Body Treatment' => 'CoolGlide Full Body',
        'CoolGlide - Female Full Body' => 'CoolGlide Full Body',
        'CoolGlide - Face Treatment' => 'CoolGlide Face',
        'CoolGlide - Half arms Treatment' => 'CoolGlide Half Arms',
        'CoolGlide - Chin Treatment' => 'CoolGlide Chin',
        'Red Carpet Laser Facial Treatment' => 'Red Carpet Laser Facial',
        'Vampire PRP Face Treatment' => 'Vampire PRP Face',
        'Slimming Drip Treatment' => 'Slimming Drip',
        'Ultrashape - Abdomen Treatment' => 'Ultrashape Abdomen',
        'Aqualyx - Double chin 2ml Treatment' => 'Aqualyx Double Chin 2ml',
        'PRGF Fusion Treatment' => 'PRGF Fusion',
        'Hair Loss Consultation' => 'Hair Loss Consult',
        'Body Contouring Consultation' => 'Body Contouring Consult',
        'Laser Hair Removal Consultation' => 'LHR Consult',
        'Skin Treatments Consultation' => 'Skin Consult',
        'Dental Services Consultation' => 'Dental Consult',
    ],

    /*
     * Tag → color key for the 21-tag vocabulary. Each key maps to a
     * CSS class suffix (.act-tag--{color}) defined in
     * public/assets/css/activity-log.css.
     */
    'tag_colors' => [
        'LEAD' => 'blue',
        'VISIT' => 'sky',
        'APT' => 'indigo',
        'RESC' => 'amber',
        'EDIT' => 'slate',
        'DEL' => 'red',
        'RCVD' => 'green',
        'CONS' => 'teal',
        'BACK' => 'orange',
        'REF' => 'rose',
        'ADJ' => 'yellow',
        'VCH' => 'violet',
        'TRMT' => 'cyan',
        'PLAN' => 'purple',
        'USER' => 'pink',
        'AUTH' => 'zinc',
        'ACCESS' => 'neutral',
        'EXPORT' => 'stone',
        'CFG' => 'stone',
        'HR' => 'emerald',
        'STK' => 'fuchsia',
        'MSG' => 'lime',
        'CASH' => 'orange',
    ],

];
