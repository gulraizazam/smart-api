<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CATEGORY_MAP = [
        'Dashboard' => [
            'dashboard_manage', 'doctor_dashboard',
        ],
        'System' => [
            'permissions_manage', 'roles_manage', 'user_types_manage',
            'settings_manage', 'user_operator_settings_manage', 'show_inactive_records',
            'logs_manage',
        ],
        'Users & Access' => [
            'users_manage', 'doctors_manage',
        ],
        'Resources' => [
            'resource_types_manage', 'resources_manage', 'resourcerotas_manage',
            'machineType_manage',
        ],
        'Locations' => [
            'regions_manage', 'cities_manage', 'towns_manage', 'locations_manage',
        ],
        'Leads' => [
            'lead_sources_manage', 'lead_statuses_manage', 'leads_manage',
        ],
        'Appointments' => [
            'appointment_statuses_manage', 'appointments_manage', 'treatments_manage',
        ],
        'Catalog' => [
            'services_manage', 'discounts_manage', 'plans_manage', 'packages_manage',
            'voucher_types_manage', 'vouchers_manage', 'memberships_manage',
            'membershiptypes_manage',
        ],
        'Patients' => [
            'patients_manage',
        ],
        'Financial' => [
            'finances_manage', 'invoices_manage', 'refunds_manage',
            'payment_modes_manage', 'cashflow_manage',
        ],
        'Forms & SMS' => [
            'custom_forms_manage', 'custom_form_feedbacks_manage',
            'sms_templates_manage', 'feedbacks_manage',
        ],
        'Targets' => [
            'staff_targets_manage', 'centre_targets_manage',
        ],
        'Business Ops' => [
            'inventory_manage', 'brand_manage', 'product_manage', 'order_manage',
            'google_reviews_manage', 'contact', 'business_closures_manage',
            'pabao_records_manage',
        ],
        'HRM' => [
            'hr_manage',
        ],
        'Reports' => [
            'leads_reports_manage', 'feedbacks_report_manage', 'appointment_reports_manage',
            'operations_reports_manage', 'centers_reports_manage', 'Hr_reports_manage',
            'finance_general_revenue_reports_manage', 'finance_revenue_breakup_reports_manage',
            'finance_ledger_reports_manage', 'staff_listing_reports_manage',
            'staff_revenue_reports_manage', 'marketing_reports_manage',
            'conversion_report_manage', 'staff_wise_arrival_manage',
            'non_converted_customers_manage', 'follow_up_manage', 'followuppatient_manage',
            'inventory_report_manage', 'upselling_report', 'consultant_revenue_report',
            'csr_dashboard_report',
        ],
    ];

    public function up(): void
    {
        if (!Schema::hasColumn('permissions', 'category')) {
            Schema::table('permissions', function (Blueprint $table): void {
                $table->string('category', 50)->nullable()->after('main_group');
                $table->index(['category', 'sort_order'], 'permissions_category_sort_idx');
            });
        }

        DB::transaction(function (): void {
            foreach (self::CATEGORY_MAP as $category => $names) {
                DB::table('permissions')
                    ->whereIn('name', $names)
                    ->where('main_group', 1)
                    ->update(['category' => $category]);
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('permissions', 'category')) {
            Schema::table('permissions', function (Blueprint $table): void {
                $table->dropIndex('permissions_category_sort_idx');
                $table->dropColumn('category');
            });
        }
    }
};
