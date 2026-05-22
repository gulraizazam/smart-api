<?php

declare(strict_types=1);

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Re-categorise every visible top-level permission group so the role
 * edit/create page lays out sections that mirror the SPA sidebar.
 *
 * Sidebar sections (and the categories the role editor will surface):
 *   Dashboard, Clinic, Catalog, Finance, Cash Flow, Inventory,
 *   Reports, People, Settings (+ Other for anything uncategorised).
 *
 * Previously the role editor showed legacy buckets like "Appointments",
 * "Financial", "Business Ops", "Users & Access", "HRM" — none of which
 * appear in the sidebar. Going forward, each module's group header is
 * filed under the sidebar section that owns it. Companion change:
 * `RoleService::CATEGORY_ORDER` updated to the new order.
 *
 * The category field only affects the role editor's section header
 * grouping — it doesn't change any grant or any `Gate::allows()` result.
 * Reversible: down() restores the original category values from a
 * snapshot saved to storage/app/migrations.
 */
return new class extends Migration
{
    /**
     * group_name => new_category
     *
     * @return array<string, string>
     */
    private function mapping(): array
    {
        return [
            // ── Dashboard ─────────────────────────────────────────────
            'dashboard_manage' => 'Dashboard',
            'dashboard_fdm' => 'Dashboard',
            'dashboard_marketing' => 'Dashboard',
            'dashboard_overview' => 'Dashboard',
            'dashboard_practitioners' => 'Dashboard',
            'doctor_dashboard' => 'Dashboard',
            'management_dashboard' => 'Dashboard',

            // ── Clinic ────────────────────────────────────────────────
            // Patient journey + scheduling, matching sidebar's Clinic group.
            'patients' => 'Clinic',
            'leads' => 'Clinic',
            'consultations' => 'Clinic',
            'treatments_manage' => 'Clinic',
            'appointments_manage' => 'Clinic',
            'business_closures_manage' => 'Clinic',
            'resourcerotas_manage' => 'Clinic',

            // ── Catalog ───────────────────────────────────────────────
            'packages_manage' => 'Catalog',
            'discounts_manage' => 'Catalog',
            'membershiptypes_manage' => 'Catalog',
            'memberships_manage' => 'Catalog',
            'plans_manage' => 'Catalog',
            'services_manage' => 'Catalog',
            'voucher_types_manage' => 'Catalog',
            'vouchers_manage' => 'Catalog',

            // ── Finance ───────────────────────────────────────────────
            'invoices_manage' => 'Finance',
            'refunds_manage' => 'Finance',
            'payment_modes_manage' => 'Finance',
            'finances_manage' => 'Finance',

            // ── Cash Flow ─────────────────────────────────────────────
            // Sidebar has Cash Flow as its own group separate from Finance.
            'cashflow_manage' => 'Cash Flow',

            // ── Inventory ─────────────────────────────────────────────
            'brand_manage' => 'Inventory',
            'product_manage' => 'Inventory',
            'order_manage' => 'Inventory',
            'inventory_manage' => 'Inventory',

            // ── Reports ───────────────────────────────────────────────
            'appointment_reports_manage' => 'Reports',
            'centers_reports_manage' => 'Reports',
            'consultant_revenue_report' => 'Reports',
            'csr_dashboard_report' => 'Reports',
            'conversion_report_manage' => 'Reports',
            'finance_general_revenue_reports_manage' => 'Reports',
            'finance_ledger_reports_manage' => 'Reports',
            'finance_revenue_breakup_reports_manage' => 'Reports',
            'follow_up_manage' => 'Reports',
            'followuppatient_manage' => 'Reports',
            'Hr_reports_manage' => 'Reports',
            'inventory_report_manage' => 'Reports',
            'leads_reports_manage' => 'Reports',
            'marketing_reports_manage' => 'Reports',
            'non_converted_customers_manage' => 'Reports',
            'operations_reports_manage' => 'Reports',
            'staff_listing_reports_manage' => 'Reports',
            'staff_revenue_reports_manage' => 'Reports',
            'staff_wise_arrival_manage' => 'Reports',
            'upselling_report' => 'Reports',

            // ── People ────────────────────────────────────────────────
            // HR sub-menu + Access sub-menu in the sidebar's People group.
            'hr_manage' => 'People',
            'doctors_manage' => 'People',
            'users_manage' => 'People',
            'user_types_manage' => 'People',
            'centre_targets_manage' => 'People',
            'staff_targets_manage' => 'People',

            // ── Settings ──────────────────────────────────────────────
            // Includes System (roles/permissions/etc.), Locations,
            // Taxonomies (resources / appointment statuses / machine types),
            // Forms & SMS, plus the few stray Business-Ops groups that
            // genuinely belong under settings.
            'settings_manage' => 'Settings',
            'logs_manage' => 'Settings',
            'permissions_manage' => 'Settings',
            'roles_manage' => 'Settings',
            'user_operator_settings_manage' => 'Settings',
            'show_inactive_records' => 'Settings',
            'custom_form_feedbacks_manage' => 'Settings',
            'custom_forms_manage' => 'Settings',
            'feedbacks_manage' => 'Settings',
            'sms_templates_manage' => 'Settings',
            'machineType_manage' => 'Settings',
            'resource_types_manage' => 'Settings',
            'resources_manage' => 'Settings',
            'locations_manage' => 'Settings',
            'cities_manage' => 'Settings',
            'regions_manage' => 'Settings',
            'towns_manage' => 'Settings',
            'lead_sources_manage' => 'Settings',
            'lead_statuses_manage' => 'Settings',
            'appointment_statuses_manage' => 'Settings',
            'pabao_records_manage' => 'Settings',
            'google_reviews_manage' => 'Settings',
        ];
    }

    public function up(): void
    {
        DB::transaction(function (): void {
            // Snapshot existing categories for down().
            $names = array_keys($this->mapping());
            $snapshot = Permission::whereIn('name', $names)
                ->where('main_group', 1)
                ->pluck('category', 'name')
                ->all();

            $this->writeSnapshot($snapshot);

            foreach ($this->mapping() as $name => $category) {
                Permission::where('name', $name)
                    ->where('main_group', 1)
                    ->update(['category' => $category]);
            }

            // Spatie + RoleService cache eviction.
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            foreach (
                [
                    'roles.permissions_mapping.v2.super',
                    'roles.permissions_mapping.v2.normal',
                    'roles.permissions_mapping.super',
                    'roles.permissions_mapping.normal',
                ] as $key
            ) {
                Cache::forget($key);
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $snapshot = $this->readSnapshot();

            foreach ($snapshot as $name => $oldCategory) {
                Permission::where('name', $name)
                    ->where('main_group', 1)
                    ->update(['category' => $oldCategory]);
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();
            foreach (
                [
                    'roles.permissions_mapping.v2.super',
                    'roles.permissions_mapping.v2.normal',
                    'roles.permissions_mapping.super',
                    'roles.permissions_mapping.normal',
                ] as $key
            ) {
                Cache::forget($key);
            }
        });
    }

    /**
     * @param array<string, string|null> $snapshot
     */
    private function writeSnapshot(array $snapshot): void
    {
        $dir = storage_path('app/migrations');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        file_put_contents(
            $dir.'/2026_05_23_120000_permission_categories.json',
            json_encode($snapshot, JSON_PRETTY_PRINT),
        );
    }

    /**
     * @return array<string, string|null>
     */
    private function readSnapshot(): array
    {
        $path = storage_path('app/migrations/2026_05_23_120000_permission_categories.json');
        if (! is_file($path)) {
            return [];
        }
        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }
};
