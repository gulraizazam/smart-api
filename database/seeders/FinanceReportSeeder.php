<?php

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class FinanceReportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Permissions has been added
        $MainPermission = Permission::create([
            'title' => 'Finance General Revenue Reports',
            'name' => 'finance_general_revenue_reports_manage',
            'guard_name' => 'web',
            'main_group' => 1,
            'parent_id' => 0,
        ]);
        Permission::insert([
            [
                'title' => 'Center performance stats by Revenue',
                'name' => 'finance_general_revenue_reports_center_performance_stats_by_revenue_finance',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Center performance stats by Service Type',
                'name' => 'finance_general_revenue_reports_center_performance_stats_by_service_type_finance',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Account Sales Report',
                'name' => 'finance_general_revenue_reports_account_sales_report',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Sale Summary Service Wise',
                'name' => 'finance_general_revenue_reports_daily_employee_stats_summary',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Sale Summary Doctors Wise',
                'name' => 'finance_general_revenue_reports_daily_employee_stats',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Sale Summary Category Wise',
                'name' => 'finance_general_revenue_reports_sales_by_service_category',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Discount Report',
                'name' => 'finance_general_revenue_reports_discount_report',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Discount Deviation Report',
                'name' => 'finance_general_revenue_reports_discount_deviation_report',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'General Revenue Report Detail',
                'name' => 'finance_general_revenue_reports_general_revenue__detail_report',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'General Revenue Report Summary',
                'name' => 'finance_general_revenue_reports_general_revenue__summary_report',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Pabau Record Revenue',
                'name' => 'finance_general_revenue_reports_pabau_record_revenue_report',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Machine wise Invoice Revenue Report',
                'name' => 'finance_general_revenue_reports_machine_wise_invoice_revenue_report',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Partner Collection Report',
                'name' => 'finance_general_revenue_reports_partner_collection_report',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Staff Wise Revenue',
                'name' => 'finance_general_revenue_reports_staff_wise_revenue',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Conversion Report',
                'name' => 'finance_general_revenue_reports_conversion_report',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Collection by Service',
                'name' => 'finance_general_revenue_reports_collection_by_service',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Machine wise Collection Report',
                'name' => 'finance_general_revenue_reports_machine_wise_collection_report',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Consume Plan Revenue Report',
                'name' => 'finance_general_revenue_reports_consume_plan_revenue_report',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],

        ]);

        // Permissions has been added
        $MainPermission = Permission::create([
            'title' => 'Finance Revenue Breakup Reports',
            'name' => 'finance_revenue_breakup_reports_manage',
            'guard_name' => 'web',
            'main_group' => 1,
            'parent_id' => 0,
        ]);

        // Permissions has been added
        $MainPermission = Permission::create([
            'title' => 'Finance Ledger Reports',
            'name' => 'finance_ledger_reports_manage',
            'guard_name' => 'web',
            'main_group' => 1,
            'parent_id' => 0,
        ]);
        Permission::insert([
            [
                'title' => 'Customer Payment Ledger',
                'name' => 'finance_ledger_reports_Customer_payment_ledger_all_entries',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Customer Treatment Package Ledger',
                'name' => 'finance_ledger_reports_customer_treatment_package_ledger',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Plan Maturity',
                'name' => 'finance_ledger_reports_plan_maturity',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'List of Advances as of Today',
                'name' => 'finance_ledger_reports_list_of_advances_as_of_today',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'List of Outstanding as of Today',
                'name' => 'finance_ledger_reports_list_of_outstanding_as_of_today',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Summarized Data of Discounts given to the Customer',
                'name' => 'finance_ledger_reports_Summarized_data_of_Discounts_given_to_the_customer',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'List of Clients Who Claimed Refunds',
                'name' => 'finance_ledger_reports_List_of_Clients_who_claimed_refunds',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
        ]);

        $role = Role::findOrFail(1);
        // Assign Permission to 'administrator' role
        $role->givePermissionTo('finance_general_revenue_reports_manage');
        $role->givePermissionTo('finance_general_revenue_reports_center_performance_stats_by_revenue_finance');
        $role->givePermissionTo('finance_general_revenue_reports_center_performance_stats_by_service_type_finance');
        $role->givePermissionTo('finance_general_revenue_reports_account_sales_report');
        $role->givePermissionTo('finance_general_revenue_reports_daily_employee_stats_summary');
        $role->givePermissionTo('finance_general_revenue_reports_daily_employee_stats');
        $role->givePermissionTo('finance_general_revenue_reports_sales_by_service_category');
        $role->givePermissionTo('finance_general_revenue_reports_collection_by_service');
        $role->givePermissionTo('finance_general_revenue_reports_machine_wise_collection_report');
        $role->givePermissionTo('finance_general_revenue_reports_discount_report');
        $role->givePermissionTo('finance_general_revenue_reports_general_revenue__detail_report');
        $role->givePermissionTo('finance_general_revenue_reports_general_revenue__summary_report');
        $role->givePermissionTo('finance_general_revenue_reports_pabau_record_revenue_report');
        $role->givePermissionTo('finance_general_revenue_reports_machine_wise_invoice_revenue_report');
        $role->givePermissionTo('finance_general_revenue_reports_staff_wise_revenue');
        $role->givePermissionTo('finance_general_revenue_reports_conversion_report');
        $role->givePermissionTo('finance_general_revenue_reports_consume_plan_revenue_report');
        $role->givePermissionTo('finance_revenue_breakup_reports_manage');
        $role->givePermissionTo('finance_ledger_reports_manage');
        $role->givePermissionTo('finance_ledger_reports_Customer_payment_ledger_all_entries');
        $role->givePermissionTo('finance_ledger_reports_customer_treatment_package_ledger');
        $role->givePermissionTo('finance_ledger_reports_list_of_advances_as_of_today');
        $role->givePermissionTo('finance_ledger_reports_list_of_outstanding_as_of_today');
        $role->givePermissionTo('finance_ledger_reports_Summarized_data_of_Discounts_given_to_the_customer');
        $role->givePermissionTo('finance_ledger_reports_List_of_Clients_who_claimed_refunds');
    }
}
