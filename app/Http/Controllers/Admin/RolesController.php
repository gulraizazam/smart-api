<?php

namespace App\Http\Controllers\Admin;

use App\HelperModule\ApiHelper;
use App\Helpers\Filters;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Validator;

class RolesController extends Controller
{
    public $success;

    public $error;

    public $unauthorized;

    public function __construct()
    {
        $this->success = config('constants.api_status.success');
        $this->error = config('constants.api_status.error');
        $this->unauthorized = config('constants.api_status.unauthorized');
    }

    /**
     * Display a listing of Role.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (! Gate::allows('roles_manage')) {
            return abort(401);
        }

        $filters = Filters::all(Auth::User()->id, 'roles');

        return view('admin.roles.index', compact('filters'));
    }

    /**
     * Display a listing of Lead_statuse.
     *
     * @param \Illuminate\Http\Request
     * @return \Illuminate\Http\Response
     */
    public function datatable(Request $request)
    {

        $filters = getFilters($request->all());

        $apply_filter = checkFilters($filters, 'roles');

        $records = [];
        $records['data'] = [];

        if (count($filters) > 0 && hasFilter($filters, 'delete') != '') {
            $ids = explode(',', $filters['delete']);
            $Roles = Role::whereIn('id', $ids)->get();

            $any_deleted = false;
            foreach ($Roles as $role) {
                if (! self::isChildExists($role->id, Auth::User()->account_id)) {
                    $any_deleted = true;
                    $role->delete();
                }
            }

            if ($any_deleted) {
                $records['status'] = true;
                $records['message'] = 'Records has been deleted successfully!';
            } else {
                $records['status'] = false;
                $records['message'] = 'One or more records are not deleted!';
            }
        }

        $where = [];

        [$orderBy, $order] = getSortBy($request);

        if (hasFilter($filters, 'name')) {
            $where[] = [
                'name',
                'like',
                '%'.$filters['name'].'%',
            ];
            Filters::put(Auth::user()->id, 'roles', 'name', $filters['name']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, 'roles', 'name');
            } else {
                if (Filters::get(Auth::user()->id, 'roles', 'name')) {
                    $where[] = [
                        'name',
                        'like',
                        '%'.Filters::get(Auth::user()->id, 'roles', 'name').'%',
                    ];
                }
            }
        }

        if (hasFilter($filters, 'commission') && is_numeric($filters['commission'])) {
            $where[] = [
                'commission',
                '=',
                $filters['commission'],
            ];
            Filters::put(Auth::user()->id, 'roles', 'commission', $filters['commission']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, 'roles', 'commission');
            } else {
                if (Filters::get(Auth::user()->id, 'roles', 'commission')) {
                    $where[] = [
                        'commission',
                        '=',
                        Filters::get(Auth::user()->id, 'roles', 'commission'),
                    ];
                }
            }
        }

        if (count($where)) {
            $iTotalRecords = Role::where($where)->count();
        } else {
            $iTotalRecords = Role::count();
        }

        [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

        if (count($where)) {
            $Roles = Role::where($where)->limit($iDisplayLength)->offset($iDisplayStart)->orderBy($orderBy, $order)->get();
        } else {
            $Roles = Role::limit($iDisplayLength)->offset($iDisplayStart)->orderBy($orderBy, $order)->get();
        }

        if ($Roles) {
            $records['data'] = $Roles;
            $records['permissions'] = [
                'edit' => Gate::allows('roles_edit'),
                'delete' => Gate::allows('roles_destroy'),
            ];
            $records['meta'] = [
                'field' => $orderBy,
                'page' => $page,
                'pages' => $pages,
                'perpage' => $iDisplayLength,
                'total' => $iTotalRecords,
                'sort' => $order,
            ];
        }

        return response()->json($records);
    }

    /**
     * Show the form for creating new Role.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (! Gate::allows('roles_create')) {
            return abort(401);
        }

        // Get list of all allowed permissions for current role.
        $allowed_permissions = Permission::join('role_has_permissions', 'role_has_permissions.permission_id', '=', 'permissions.id')
            ->get()->pluck('name', 'id');
        if (! $allowed_permissions) {
            $allowed_permissions = [];
        }

        $mapping = $this->getAllPermissionsMapping();

        $permissions = $mapping['permissions'];
        $dashboard_permissions = $mapping['dashboard_permissions'];
        $reports_permissions = $mapping['reports_permissions'];

        $permissions_mapping = $mapping['permissions_mapping'];
        $dashboard_permissions_mapping = $mapping['dashboard_permissions_mapping'];
        $reports_permissions_mapping = $mapping['reports_permissions_mapping'];

        return ApiHelper::makeResponse([
            'permissions' => $permissions,
            'dashboard_permissions' => $dashboard_permissions,
            'reports_permissions' => $reports_permissions,
            'permissions_mapping' => $permissions_mapping,
            'dashboard_permissions_mapping' => $dashboard_permissions_mapping,
            'reports_permissions_mapping' => $reports_permissions_mapping,
            'allowed_permissions' => $allowed_permissions,
        ], 'admin.roles.create');

    }

    /**
     * Store a newly created Role in storage.
     */
    public function store(Request $request)
    {
        if (! Gate::allows('roles_create')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }

        $validator = $this->verifyFields($request);

        if ($validator->fails()) {
            return ApiHelper::apiResponse($this->success, $validator->messages()->first(), false);
        }
        unset($request['DataTables_Table_0_length']);
        $role = Role::create($request->except('permission'));
        $permissions = $request->input('permission') ? $request->input('permission') : [];
        $role->givePermissionTo($permissions);

        session()->flash('success', 'Record has been created successfully.');

        return ApiHelper::apiResponse($this->success, 'Record has been created successfully.');
    }

    /**
     * Prepare Permissions to display in table
     *
     * @param  (void)
     * @return (array) $array
     */
    protected function preparePermissionsMapping()
    {
        /*
         * Note: Mapping will go like below examples
         * permissions_create
         * permissions_edit
         * permissions_active
         * permissions_inactive
         * permissions_destroy
         * users_change_password
         */
        return [
            'create' => 'Create',
            'edit' => 'Edit',
            'active' => 'Active',
            'inactive' => 'Inactive',
            'destroy' => 'Delete',
            'sort' => 'Sort',
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
            'payment' => 'Add Payment',
            'detail' => 'Detail',
            'service_delete' => 'Plan Service Delete',
            'cash_edit' => 'Plan Cash Edit',

            'cash_edit_payment_mode' => 'Plan Cash Edit Payment Mode',
            'cash_edit_amount' => 'Plan Cash Edit Amount',
            'cash_edit_date' => 'Plan Cash Edit Date',

            'cash_delete' => 'Plan Cash Delete',
            'log' => 'Log',
            'log_excel' => 'Generate Invoice For Outrange',
            'sms_log' => 'Sms Log',
        ];
    }

    /**
     * Prepare Dashboard Permissions to display in table
     *
     * @param  (void)
     * @return (array) $array
     */
    protected function prepareDashboardPermissionsMapping()
    {
        /*
         * Note: Mapping will go like below examples
         * permissions_create
         * permissions_edit
         * permissions_active
         * permissions_inactive
         * permissions_destroy
         * users_change_password
         */
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
            'doctor_wise_conversion' =>'Doctor Wise Conversion',
            'unattendend_report' => 'Unattended Payments',
            'overdue_treatments' => 'Overdue Treatments'
        ];
    }

    /**
     * Prepare Reports Permissions to display in table
     *
     * @param  (void)
     * @return (array) $array
     */
    protected function prepareReportsPermissionsMapping()
    {
        /*
         * Note: Mapping will go like below examples
         * permissions_create
         * permissions_edit
         * permissions_active
         * permissions_inactive
         * permissions_destroy
         * users_change_password
         */
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
        ];
    }

    /**
     * Validate form fields
     *
     * @return Validator $validator;
     */
    protected function verifyFields(Request $request)
    {
        return $validator = Validator::make($request->all(), [
            'name' => 'required',
        ]);
    }

    /**
     * Show the form for editing Role.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (! Gate::allows('roles_edit')) {
            return abort(401);
        }

        $role = Role::findOrFail($id);

        // Get list of all allowed permissions for current role.
        $allowed_permissions = Permission::join('role_has_permissions', 'role_has_permissions.permission_id', '=', 'permissions.id')
            ->where(['role_has_permissions.role_id' => $role->id])
            ->get()->pluck('name', 'id');
        if (! $allowed_permissions) {
            $allowed_permissions = [];
        }

        $mapping = $this->getAllPermissionsMapping();

        $permissions = $mapping['permissions'];
        $dashboard_permissions = $mapping['dashboard_permissions'];
        $reports_permissions = $mapping['reports_permissions'];

        $permissions_mapping = $mapping['permissions_mapping'];
        $dashboard_permissions_mapping = $mapping['dashboard_permissions_mapping'];
        $reports_permissions_mapping = $mapping['reports_permissions_mapping'];

        return ApiHelper::makeResponse([
            'role' => $role,
            'allowed_permissions' => $allowed_permissions,
            'dashboard_permissions_mapping' => $dashboard_permissions_mapping,
            'dashboard_permissions' => $dashboard_permissions,
            'permissions' => $permissions,
            'permissions_mapping' => $permissions_mapping,
            'reports_permissions_mapping' => $reports_permissions_mapping,
            'reports_permissions' => $reports_permissions,
        ], 'admin.roles.edit');

    }

    /**
     * Get All Permissions Mappings
     *
     * @param  (void)
     * @return (array) $array
     */
    protected function getAllPermissionsMapping()
    {
        $notInArray = [
            'dashboard_manage', 'leads_reports_manage', 'appointment_reports_manage', 'operations_reports_manage', 'centers_reports_manage', 'Hr_reports_manage', 'finance_general_revenue_reports_manage', 'finance_revenue_breakup_reports_manage', 'finance_ledger_reports_manage', 'staff_listing_reports_manage', 'staff_revenue_reports_manage', 'marketing_reports_manage','conversion_report_manage','staff_wise_arrival_manage','non_converted_customers_manage'
        ,'follow_up_manage'];
        $notInNamesArray = [
            'view_inactive_users', 'view_inactive_appointment_statuses', 'view_inactive_centres', 'view_inactive_cities', 'view_inactive_discounts', 'view_inactive_doctors', 'view_inactive_lead_sources', 'view_inactive_leads', 'view_inactive_lead_statuses', 'view_inactive_machine_types', 'view_inactive_packages', 'view_inactive_patients', 'view_inactive_payment_modes', 'view_inactive_plans',
            'view_inactive_products', 'view_inactive_regions', 'view_inactive_custom_forms', 'view_inactive_towns', 'view_inactive_resources', 'view_inactive_rota', 'view_inactive_rotas', 'view_inactive_services', 'view_inactive_sms_templates',
        ];
        if (Auth::user()->hasRole('Super-Admin')) {
            $group_permissions = Permission::where(['main_group' => 1, 'status' => 1])
                ->whereNotIn('name', $notInArray)
                ->get();
            $sub_permissions = Permission::whereIn('parent_id', Permission::where(['main_group' => 1, 'status' => 1])->whereNotIn('name', $notInArray)->pluck('id', 'name'))->get()->keyBy('id');
        } else {
            $group_permissions = Permission::where(['main_group' => 1, 'status' => 1])
                ->whereNotIn('name', $notInArray)
                ->whereNotIn('name', $notInNamesArray)
                ->get();
            $sub_permissions = Permission::whereIn('parent_id', Permission::where(['main_group' => 1, 'status' => 1])->whereNotIn('name', $notInArray)
                ->whereNotIn('name', $notInNamesArray)
                ->pluck('id', 'name'))->get()->keyBy('id');
        }
        $permissions = [];
        if ($group_permissions) {
            foreach ($group_permissions as $group_permission) {
                $permissions[$group_permission->id] = [
                    'id' => $group_permission->id,
                    'title' => $group_permission->title,
                    'name' => $group_permission->name,
                    'parent_id' => $group_permission->parent_id,
                    'children' => [],
                    'key' => Str::replaceLast('manage', '', $group_permission->name),
                ];
                if ($sub_permissions) {
                    foreach ($sub_permissions as $sub_permission) {
                        if (array_key_exists($sub_permission->parent_id, $permissions)) {
                            $permissions[$sub_permission->parent_id]['children'][$sub_permission->name] = [
                                'id' => $sub_permission->id,
                                'title' => $sub_permission->title,
                                'name' => $sub_permission->name,
                                'parent_id' => $sub_permission->parent_id,
                            ];
                        }
                    }
                }
            }
        }
        /*
         * Dashboard Permissions
         */
        $whereIn = [
            'dashboard_manage',
        ];
        $dashboard_group_permissions = Permission::where(['main_group' => 1, 'status' => 1])->
        whereIn('name', $whereIn)
            ->get();

        $dashboard_sub_permissions = Permission::whereIn('parent_id', Permission::where(['main_group' => 1, 'status' => 1])->whereIn('name', $whereIn)->pluck('id', 'name'))->get()->keyBy('id');

        $dashboard_permissions = [];
        if ($dashboard_group_permissions) {
            foreach ($dashboard_group_permissions as $group_permission) {
                $dashboard_permissions[$group_permission->id] = [
                    'id' => $group_permission->id,
                    'title' => $group_permission->title,
                    'name' => $group_permission->name,
                    'parent_id' => $group_permission->parent_id,
                    'children' => [],
                    'key' => Str::replaceLast('manage', '', $group_permission->name),
                ];

                if ($dashboard_sub_permissions) {
                    foreach ($dashboard_sub_permissions as $sub_permission) {
                        if (array_key_exists($sub_permission->parent_id, $dashboard_permissions)) {
                            $dashboard_permissions[$sub_permission->parent_id]['children'][$sub_permission->name] = [
                                'id' => $sub_permission->id,
                                'title' => $sub_permission->title,
                                'name' => $sub_permission->name,
                                'parent_id' => $sub_permission->parent_id,
                            ];
                        }
                    }
                }
            }
        }

        /*
         * Reports Permissions
         */
        $whereIn = [
            'leads_reports_manage', 'appointment_reports_manage', 'operations_reports_manage', 'centers_reports_manage', 'Hr_reports_manage', 'finance_general_revenue_reports_manage', 'finance_revenue_breakup_reports_manage', 'finance_ledger_reports_manage', 'staff_listing_reports_manage', 'staff_revenue_reports_manage', 'marketing_reports_manage'
        ,'conversion_report_manage','staff_wise_arrival_manage','non_converted_customers_manage','follow_up_manage'];
        $reports_group_permissions = Permission::where(['main_group' => 1, 'status' => 1])->
        whereIn('name', $whereIn)
            ->get();
        $report_sub_permissions = Permission::whereIn('parent_id', Permission::where(['main_group' => 1, 'status' => 1])->whereIn('name', $whereIn)->pluck('id', 'name'))->get()->keyBy('id');

        $reports_permissions = [];
        if ($reports_group_permissions) {
            foreach ($reports_group_permissions as $group_permission) {
                $reports_permissions[$group_permission->id] = [
                    'id' => $group_permission->id,
                    'title' => $group_permission->title,
                    'name' => $group_permission->name,
                    'parent_id' => $group_permission->parent_id,
                    'children' => [],
                    'key' => Str::replaceLast('manage', '', $group_permission->name),
                ];

                if ($report_sub_permissions) {
                    foreach ($report_sub_permissions as $sub_permission) {
                        if (array_key_exists($sub_permission->parent_id, $reports_permissions)) {
                            $reports_permissions[$sub_permission->parent_id]['children'][$sub_permission->name] = [
                                'id' => $sub_permission->id,
                                'title' => $sub_permission->title,
                                'name' => $sub_permission->name,
                                'parent_id' => $sub_permission->parent_id,
                            ];
                        }
                    }
                }
            }
        }

        $permissions_mapping = $this->preparePermissionsMapping();
        $dashboard_permissions_mapping = $this->prepareDashboardPermissionsMapping();
        $reports_permissions_mapping = $this->prepareReportsPermissionsMapping();

        return [
            'permissions' => $permissions,
            'dashboard_permissions' => $dashboard_permissions,
            'reports_permissions' => $reports_permissions,
            'permissions_mapping' => $permissions_mapping,
            'dashboard_permissions_mapping' => $dashboard_permissions_mapping,
            'reports_permissions_mapping' => $reports_permissions_mapping,
        ];
    }

    /**
     * Update Role in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        if (! Gate::allows('roles_edit')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }

        try {

            $validator = $this->verifyFields($request);

            if ($validator->fails()) {
                return ApiHelper::apiResponse($this->success, $validator->messages()->first(), false);
            }

            unset($request['DataTables_Table_0_length']);
            $role = Role::findOrFail($id);
            $role->update($request->except('permission'));
            $permissions = $request->input('permission') ? $request->input('permission') : [];

            $role->syncPermissions($permissions);

            session()->flash('success', 'Record has been updated successfully.');

            return ApiHelper::apiResponse($this->success, 'Record has been updated successfully.');
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Remove Role from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy($id)
    {
        if (! Gate::allows('roles_destroy')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }

        $role = Role::findOrFail($id);

        if (! $role) {

            session()->flash('success', 'Resource not found.', false);

            return ApiHelper::apiResponse($this->success, 'Resource not found.', false);
        }

        // Check if child records exists or not, If exist then disallow to delete it.
        if (self::isChildExists($id, Auth::User()->account_id)) {

            session()->flash('success', 'Child records exist, unable to delete resource.');

            return ApiHelper::apiResponse($this->success, 'Child records exist, unable to delete resource.', false);
        }

        $role->delete();

        session()->flash('success', 'Record has been deleted successfully.');

        return ApiHelper::apiResponse($this->success, 'Record has been deleted successfully.');
    }

    /**
     * Check if child records exist
     *
     * @param  (int)  $id
     * @return (boolean)
     */
    public static function isChildExists($id, $account_id)
    {
        if (DB::table('role_has_users')->where('role_id', '=', $id)->count()) {
            return true;
        }

        return false;
    }
}
