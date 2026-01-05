<?php

namespace App\Services\UserManagement;

use App\Models\Permission;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class RoleService
{
    private const CACHE_TTL = 3600; // 1 hour
    private const CACHE_KEY_PERMISSIONS_MAPPING = 'roles.permissions_mapping';

    /**
     * Get paginated roles for datatable
     */
    public function getDatatableData(array $params): array
    {
        $query = Role::query();
        
        $totalBeforeFilter = Role::count();
        
        if (!empty($params['name'])) {
            $query->where('name', 'LIKE', "%{$params['name']}%");
        }
        
        if (!empty($params['commission']) && is_numeric($params['commission'])) {
            $query->where('commission', $params['commission']);
        }
        
        $totalFiltered = $query->count();
        
        $orderBy = $params['orderBy'] ?? 'name';
        $order = $params['order'] ?? 'asc';
        
        $roles = $query
            ->orderBy($orderBy, $order)
            ->offset($params['offset'] ?? 0)
            ->limit($params['limit'] ?? 30)
            ->get();

        return [
            'data' => $roles,
            'total' => $totalFiltered,
            'totalBeforeFilter' => $totalBeforeFilter,
        ];
    }

    /**
     * Get user permissions for datatable actions
     */
    public function getUserPermissions(): array
    {
        return [
            'edit' => Gate::allows('roles_edit'),
            'duplicate' => Gate::allows('roles_duplicate'),
            'delete' => Gate::allows('roles_destroy'),
        ];
    }

    /**
     * Get all permissions mapping for role create/edit forms
     */
    public function getAllPermissionsMapping(): array
    {
        $cacheKey = self::CACHE_KEY_PERMISSIONS_MAPPING . '.' . (Auth::user()->hasRole('Super-Admin') ? 'super' : 'normal');
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () {
            return $this->buildPermissionsMapping();
        });
    }

    /**
     * Build permissions mapping structure
     */
    private function buildPermissionsMapping(): array
    {
        $notInArray = [
            'dashboard_manage', 'leads_reports_manage', 'feedbacks_report_manage', 
            'appointment_reports_manage', 'operations_reports_manage', 'centers_reports_manage', 
            'Hr_reports_manage', 'finance_general_revenue_reports_manage', 
            'finance_revenue_breakup_reports_manage', 'finance_ledger_reports_manage', 
            'staff_listing_reports_manage', 'staff_revenue_reports_manage', 
            'marketing_reports_manage', 'conversion_report_manage', 'staff_wise_arrival_manage', 
            'non_converted_customers_manage', 'follow_up_manage', 'followuppatient_manage'
        ];
        
        $notInNamesArray = [
            'view_inactive_users', 'view_inactive_appointment_statuses', 'view_inactive_centres',
            'view_inactive_cities', 'view_inactive_discounts', 'view_inactive_doctors',
            'view_inactive_lead_sources', 'view_inactive_leads', 'view_inactive_lead_statuses',
            'view_inactive_machine_types', 'view_inactive_packages', 'view_inactive_patients',
            'view_inactive_payment_modes', 'view_inactive_plans', 'view_inactive_products',
            'view_inactive_regions', 'view_inactive_custom_forms', 'view_inactive_towns',
            'view_inactive_resources', 'view_inactive_rota', 'view_inactive_rotas',
            'view_inactive_services', 'view_inactive_sms_templates',
        ];

        $isSuperAdmin = Auth::user()->hasRole('Super-Admin');

        // General permissions
        $permissions = $this->buildPermissionGroup($notInArray, $notInNamesArray, $isSuperAdmin, false);
        
        // Dashboard permissions
        $dashboardWhereIn = ['dashboard_manage'];
        $dashboard_permissions = $this->buildPermissionGroup($dashboardWhereIn, [], $isSuperAdmin, true);
        
        // Reports permissions
        $reportsWhereIn = [
            'leads_reports_manage', 'feedbacks_report_manage', 'appointment_reports_manage',
            'operations_reports_manage', 'centers_reports_manage', 'Hr_reports_manage',
            'finance_general_revenue_reports_manage', 'finance_revenue_breakup_reports_manage',
            'finance_ledger_reports_manage', 'staff_listing_reports_manage',
            'staff_revenue_reports_manage', 'marketing_reports_manage', 'conversion_report_manage',
            'staff_wise_arrival_manage', 'non_converted_customers_manage', 'follow_up_manage',
            'followuppatient_manage'
        ];
        $reports_permissions = $this->buildPermissionGroup($reportsWhereIn, [], $isSuperAdmin, true);

        return [
            'permissions' => $permissions,
            'dashboard_permissions' => $dashboard_permissions,
            'reports_permissions' => $reports_permissions,
            'permissions_mapping' => $this->getPermissionsMapping(),
            'dashboard_permissions_mapping' => $this->getDashboardPermissionsMapping(),
            'reports_permissions_mapping' => $this->getReportsPermissionsMapping(),
        ];
    }

    /**
     * Build a permission group with parent-child structure
     */
    private function buildPermissionGroup(array $filterArray, array $notInNamesArray, bool $isSuperAdmin, bool $useWhereIn): array
    {
        $baseQuery = Permission::where(['main_group' => 1, 'status' => 1]);
        
        if ($useWhereIn) {
            $baseQuery->whereIn('name', $filterArray);
        } else {
            $baseQuery->whereNotIn('name', $filterArray);
            if (!$isSuperAdmin) {
                $baseQuery->whereNotIn('name', $notInNamesArray);
            }
        }
        
        $groupPermissions = $baseQuery->get();
        $parentIds = $groupPermissions->pluck('id')->toArray();
        
        // Get all sub-permissions in one query, grouped by parent_id for efficient lookup
        $subPermissions = Permission::whereIn('parent_id', $parentIds)
            ->get()
            ->groupBy('parent_id');

        $result = [];
        foreach ($groupPermissions as $groupPermission) {
            $parentId = $groupPermission->id;
            
            $result[$parentId] = [
                'id' => $parentId,
                'title' => $groupPermission->title,
                'name' => $groupPermission->name,
                'parent_id' => $groupPermission->parent_id,
                'children' => [],
                'key' => Str::replaceLast('manage', '', $groupPermission->name),
            ];

            // Get children for this parent (already grouped by parent_id)
            $children = $subPermissions->get($parentId, collect());
            foreach ($children as $subPermission) {
                $result[$parentId]['children'][$subPermission->name] = [
                    'id' => $subPermission->id,
                    'title' => $subPermission->title,
                    'name' => $subPermission->name,
                    'parent_id' => $subPermission->parent_id,
                ];
            }
        }

        return $result;
    }

    /**
     * Get allowed permissions for a role
     */
    public function getAllowedPermissions(?int $roleId = null): array
    {
        $query = Permission::join('role_has_permissions', 'role_has_permissions.permission_id', '=', 'permissions.id');
        
        if ($roleId) {
            $query->where('role_has_permissions.role_id', $roleId);
        }
        
        $permissions = $query->get()->pluck('name', 'id')->toArray();
        
        return $permissions ?: [];
    }

    /**
     * Create a new role
     */
    public function create(array $data): Role
    {
        $permissions = $data['permission'] ?? [];
        unset($data['permission'], $data['DataTables_Table_0_length']);
        
        $role = Role::create($data);
        $role->givePermissionTo($permissions);
        
        $this->clearCache();

        return $role;
    }

    /**
     * Update an existing role
     */
    public function update(int $id, array $data): Role
    {
        $role = $this->findOrFail($id);
        
        $permissions = $data['permission'] ?? [];
        unset($data['permission'], $data['DataTables_Table_0_length']);
        
        $role->update($data);
        $role->syncPermissions($permissions);
        
        $this->clearCache();

        return $role;
    }

    /**
     * Duplicate a role
     */
    public function duplicate(array $data): Role
    {
        $permissions = $data['permission'] ?? [];
        unset($data['permission'], $data['DataTables_Table_0_length']);
        
        $role = Role::create($data);
        $role->givePermissionTo($permissions);
        
        $this->clearCache();

        return $role;
    }

    /**
     * Delete a role
     */
    public function delete(int $id): bool
    {
        $role = $this->findOrFail($id);
        
        if ($this->hasUsers($id)) {
            return false;
        }
        
        $deleted = $role->delete();
        $this->clearCache();

        return $deleted;
    }

    /**
     * Bulk delete roles (only those without users)
     */
    public function bulkDelete(array $ids): array
    {
        $deleted = 0;
        $skipped = 0;
        
        $roles = Role::whereIn('id', $ids)->get();
        
        foreach ($roles as $role) {
            if (!$this->hasUsers($role->id)) {
                $role->delete();
                $deleted++;
            } else {
                $skipped++;
            }
        }
        
        if ($deleted > 0) {
            $this->clearCache();
        }

        return [
            'deleted' => $deleted,
            'skipped' => $skipped,
        ];
    }

    /**
     * Check if role has assigned users
     */
    public function hasUsers(int $roleId): bool
    {
        return DB::table('role_has_users')->where('role_id', $roleId)->exists();
    }

    /**
     * Find role by ID or fail
     */
    public function findOrFail(int $id): Role
    {
        return Role::findOrFail($id);
    }

    /**
     * Clear role-related cache
     */
    private function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY_PERMISSIONS_MAPPING . '.super');
        Cache::forget(self::CACHE_KEY_PERMISSIONS_MAPPING . '.normal');
    }

    /**
     * Get permissions mapping for display
     */
    private function getPermissionsMapping(): array
    {
        return [
            'create' => 'Create',
            'edit' => 'Edit',
            'active' => 'Active',
            'inactive' => 'Inactive',
            'destroy' => 'Delete',
            'sort' => 'Sort',
            'assign' => 'Assign',
            'change_password' => 'Change Password',
            'import' => 'Import',
            'export' => 'Export',
            'export_today' => 'Today',
            'export_this_month' => 'This Month',
            'export_all' => 'Export All',
            'patient_card' => 'Patient Card',
            'invoice_display' => 'Display Invoice',
            'convert' => 'Convert',
            'lead_status' => 'Update Lead Status',
            'city' => 'Update City',
            'junk' => 'Junk Leads',
            'consultancy' => 'Manage Consultancy',
            'services' => 'Manage Treatments',
            'appointment_status' => 'Update Appointment Status',
            'invoice' => 'Generate Invoice',
            'image_manage' => 'Images',
            'image_upload' => 'Image Upload',
            'image_destroy' => 'Image Delete',
            'measurement_manage' => 'Measurements',
            'measurement_create' => 'Measurement Create',
            'measurement_edit' => 'Measurement Edit',
            'medical_form_manage' => 'Medical Form',
            'medical_create' => 'Medical Form Create',
            'medical_edit' => 'Medical Form Edit',
            'plans_create' => 'Plans Create',
            'allocate' => 'Allocate',
            'calender' => 'Calender',
            'create_general' => 'Create General',
            'create_measurement' => 'Create Measurement',
            'create_medical_history_form' => 'Create Medical History Form',
            'preview' => 'Preview',
            'submit' => 'Submit',
            'cancel' => 'Cancel',
            'refund' => 'Refund',
            'appointment_manage' => 'Appointment Manage',
            'customform_manage' => 'Custom Form Manage',
            'customform_create' => 'Custom Form Create',
            'customform_edit' => 'Custom Form Edit',
            'document_manage' => 'Document Manage',
            'document_create' => 'Document Create',
            'document_edit' => 'Document Edit',
            'document_destroy' => 'Document Delete',
            'plan_manage' => 'Plan Manage',
            'plan_create' => 'Plan Create',
            'plan_inactive' => 'Plan Inactive',
            'plan_active' => 'Plan Active',
            'plan_destroy' => 'Plan Delete',
            'plan_edit' => 'Plan Edit',
            'plan_service_delete' => 'Patient Plan Service Delete',
            'plan_cash_edit' => 'Patient Plan Cash Edit',
            'plan_cash_edit_payment_mode' => 'Patient Plan Cash Edit Payment Mode',
            'plan_cash_edit_amount' => 'Patient Plan Cash Edit Amount',
            'plan_cash_edit_date' => 'Patient Plan Cash Edit Date',
            'plan_cash_delete' => 'Patient Plan Cash Delete',
            'plan_log' => 'Plan Log',
            'plan_log_excel' => 'Plan Log Excel',
            'plan_sms_log' => 'Plan Sms Log',
            'finance_manage' => 'Finance Manage',
            'finance_create' => 'Finance Create',
            'invoice_manage' => 'Invoice Manage',
            'invoice_cancel' => 'Invoice Cancel',
            'invoice_log' => 'Invoice Log',
            'invoice_log_excel' => 'Invoice Log Excel',
            'invoice_sms_log' => 'Invoice Sms Log',
            'refund_manage' => 'Refund Manage',
            'refund_refund' => 'Patient Refund',
            'add_referrals' => 'Assign Referrals',
            'payment' => 'Add Payment',
            'detail' => 'Detail',
            'service_delete' => 'Plan Service Delete',
            'duplicate' => 'Duplicate',
            'cash_edit' => 'Plan Cash Edit',
            'cash_edit_payment_mode' => 'Plan Cash Edit Payment Mode',
            'cash_edit_amount' => 'Plan Cash Edit Amount',
            'cash_edit_date' => 'Plan Cash Edit Date',
            'cash_delete' => 'Plan Cash Delete',
            'log' => 'Log',
            'log_excel' => 'Generate Invoice For Outrange',
            'edit_appointment_after_arrived' => 'Edit Appointment After Arrived',
            'sms_log' => 'Sms Log',
            'edit_sold_by' => 'Plan Edit Sold By',
            'add_stock' => 'Add Stock',
            'stock_detail' => 'Stock Detail',
            'sale_price' => 'Sale Price',
            'transfer_manage' => 'Transfer',
            'inventory_refund_manage' => 'Inventory Refund',
        ];
    }

    /**
     * Get dashboard permissions mapping
     */
    private function getDashboardPermissionsMapping(): array
    {
        return [
            'collection_by_centre' => 'Collection by Centre',
            'my_collection_by_centre' => 'My Collection by Centre',
            'revenue_by_centre' => 'Revenue by Centre',
            'my_revenue_by_centre' => 'My Revenue by Centre',
            'revenue_by_service' => 'Revenue by Service',
            'my_revenue_by_service' => 'My Revenue by Service',
            'states' => 'Stats',
            'recent_activities' => 'Recent Activities',
            'upcomings' => 'Upcomings',
            'my_appointment_by_type' => 'My Appointments by Type',
            'appointment_by_type' => 'Treatment by Status',
            'appointment_by_status' => 'Consultancy by Status',
            'my_appointment_by_status' => 'My Appointments by Status',
            'staff_wise_arrival' => 'Staff Wise Arrival',
            'doctor_wise_conversion' => 'Doctor Wise Conversion',
            'doctor_wise_feedback' => 'Manage Feedback',
            'unattended_report' => 'Unattended Payments',
            'overdue_treatments' => 'Overdue Treatments',
            'appointments_report' => 'Appointments Report',
            'upselling_report' => 'Doctors Upselling Report',
        ];
    }

    /**
     * Get reports permissions mapping
     */
    private function getReportsPermissionsMapping(): array
    {
        return [
            'general_report' => 'General Report',
            'general_summary_report' => 'General Report Summary',
            'summary_report_by_lead_status' => 'Summary Report By Lead Status',
            'lead_status_percentage' => 'Lead Status Percentage',
            'now_show_report' => 'Now Show List Report',
            'staff_appointment' => 'Staff Wise Appointment Report',
            'referred_by_staff_appointment' => 'Staff Wise (Referred By) Appointment Report',
            'empolyee_summary' => 'Appointment Summary Report',
            'summary_by_service' => 'Appointments Summary by Service',
            'summary_by_appointment_status' => 'Appointments Summary by Status',
            'clients_by_appointment_status' => 'Patient by Appointment Status (Date Wise)',
            'center_target_report' => 'Center Target Report',
            'operations_company_health' => 'Company Health Report',
            'Highest_paying_clients' => 'Highest Paying Clients',
            'List_of_refunds_for_a_certain_period_date_based' => 'List of refunds for a certain period (date based)',
            'List_of_services_that_CAN_be_offered_Complimentary' => 'List of services that CAN be offered Complimentary',
            'List_of_services_that_CAN_not_be_offered_Complimentary' => 'List of services that CAN NOT be offered Complimentary',
            'conversion_report_consultancy' => 'Conversion Report For Consultancy',
            'conversion_report_treatment' => 'Conversion Report For Treatment',
            'client_with_Completed_treatment' => 'Clients with completed treatments',
            'dar_report' => 'DAR Report',
            'complimentory_report' => 'Complimentory Treatment',
            'dtr_report' => 'DTR Report',
            'operations_tax_calculation_report' => 'Tax Calculation Report',
            'client_with_not_Completed_treatment' => 'Clients with not completed treatments',
            'clients_took_treatments_particular_month' => 'Clients with treatments in a particular month',
            'clients_with_birthday_days' => 'Clients with birthday + x days',
            'reports_for_calculating_incentives' => 'Reports For Calculating Incentives',
            'reports_for_calculating_incentives_detail' => 'Reports For Calculating Incentives Detail',
            'revenue_generated_by_operators_application_user' => 'Revenue Generated By Operators (Application User)',
            'revenue_generated_by_consultants_practitioner' => 'Revenue Generated By Consultants (Practitioner)',
            'center_performance_stats_by_revenue_finance' => 'Center performance stats by Revenue',
            'center_performance_stats_by_service_type_finance' => 'Center performance stats by Service Type',
            'account_sales_report' => 'Account Sales Report',
            'collection_by_service' => 'Collection by Service',
            'daily_employee_stats_summary' => 'Sale Summary Service Wise',
            'daily_employee_stats' => 'Sale Summary Doctors Wise',
            'sales_by_service_category' => 'Sale Summary Category Wise',
            'discount_report' => 'Discount Report',
            'discount_deviation_report' => 'Discount Deviation Report',
            'general_revenue__detail_report' => 'General Revenue Detail Report',
            'general_revenue__summary_report' => 'General Revenue Summary Report',
            'pabau_record_revenue_report' => 'Pabau Record Revenue Report',
            'machine_wise_collection_report' => 'Machine wise Collection Report',
            'machine_wise_invoice_revenue_report' => 'Machine wise Invoice Revenue Report',
            'partner_collection_report' => 'Partner Collection Report',
            'staff_wise_revenue' => 'Staff Wise Revenue',
            'conversion_report' => 'Conversion Report',
            'conversion_report_manage' => 'Manage Conversion Report',
            'consume_plan_revenue_report' => 'Consume Plan Revenue Report',
            'Customer_payment_ledger_all_entries' => 'Customer Payment Ledger',
            'customer_treatment_package_ledger' => 'Customer Treatment Package Ledger',
            'plan_maturity' => 'Plan Maturity Report',
            'list_of_advances_as_of_today' => 'List of Advances as of Today',
            'list_of_outstanding_as_of_today' => 'List of Outstanding as of Today',
            'Summarized_data_of_Discounts_given_to_the_customer' => 'Summarized Data of Discounts given to the Customer',
            'List_of_Clients_who_claimed_refunds' => 'List of Clients Who Claimed Refunds',
            'region_wise_staff_list' => 'Region Wise Staff List',
            'centre_wise_staff_list' => 'Centre Wise Staff List',
            'center_performance_stats_by_revenue' => 'Staff Revenue Centre Wise',
            'center_performance_stats_by_service_type' => 'Staff Revenue by Service Type',
            'compliance_reports' => 'Compliance Report',
            'rescheduled_count_report' => 'Appointment Rescheduled Count Report',
            'activity_report' => 'Appointment Activity Logs Report',
            'services_duplicate' => 'Duplicate',
        ];
    }
}
