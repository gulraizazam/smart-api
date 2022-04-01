<?php

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class OperationReportSeeder extends Seeder
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
            'title' => 'Operation Reports',
            'name' => 'operations_reports_manage',
            'guard_name' => 'web',
            'main_group' => 1,
            'parent_id' => 0,
        ]);
        Permission::insert([
            [
                'title' => 'Center Target Report',
                'name' => 'operations_reports_center_target_report',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Company Health Report',
                'name' => 'operations_reports_operations_company_health',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Highest Paying Clients',
                'name' => 'operations_reports_Highest_paying_clients',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'List of refunds for a certain period (date based)',
                'name' => 'operations_reports_List_of_refunds_for_a_certain_period_date_based',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'List of services that CAN be offered Complimentary',
                'name' => 'operations_reports_List_of_services_that_CAN_be_offered_Complimentary',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'List of services that CAN NOT be offered Complimentary',
                'name' => 'operations_reports_List_of_services_that_CAN_not_be_offered_Complimentary',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Conversion Report For Consultancy',
                'name' => 'operations_reports_conversion_report_consultancy',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Conversion Report For Treatment',
                'name' => 'operations_reports_conversion_report_treatment',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'DAR Report',
                'name' => 'operations_reports_dar_report',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Complimentory Treatment Report',
                'name' => 'operations_reports_complimentory_report',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'DTR Report',
                'name' => 'operations_reports_dtr_report',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],

        ]);

        $role = Role::findOrFail(1);

        // Assign Permission to 'administrator' role
        $role->givePermissionTo('operations_reports_manage');
        $role->givePermissionTo('operations_reports_center_target_report');
        $role->givePermissionTo('operations_reports_operations_company_health');
        $role->givePermissionTo('operations_reports_Highest_paying_clients');
        $role->givePermissionTo('operations_reports_List_of_refunds_for_a_certain_period_date_based');
        $role->givePermissionTo('operations_reports_List_of_services_that_CAN_be_offered_Complimentary');
        $role->givePermissionTo('operations_reports_List_of_services_that_CAN_not_be_offered_Complimentary');
        $role->givePermissionTo('operations_reports_conversion_report_consultancy');
        $role->givePermissionTo('operations_reports_conversion_report_treatment');
        $role->givePermissionTo('operations_reports_dar_report');
        $role->givePermissionTo('operations_reports_complimentory_report');
        $role->givePermissionTo('operations_reports_dtr_report');
    }
}
