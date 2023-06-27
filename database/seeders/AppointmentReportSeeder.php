<?php

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AppointmentReportSeeder extends Seeder
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
            'title' => 'Appointment Reports',
            'name' => 'appointment_reports_manage',
            'guard_name' => 'web',
            'main_group' => 1,
            'parent_id' => 0,
        ]);
        Permission::insert([
            [
                'title' => 'General Report',
                'name' => 'appointment_reports_general_report',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'General Report Summary',
                'name' => 'appointment_reports_general_summary_report',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Staff Wise Appointment Report',
                'name' => 'appointment_reports_staff_appointment',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Staff Wise (Referred By) Appointment Report',
                'name' => 'appointment_reports_referred_by_staff_appointment',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Appointment Summary Report',
                'name' => 'appointment_reports_empolyee_summary',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Appointments Summary by Service',
                'name' => 'appointment_reports_summary_by_service',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Appointments Summary by Status',
                'name' => 'appointment_reports_summary_by_appointment_status',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Patient by Appointment Status (Date Wise)',
                'name' => 'appointment_reports_clients_by_appointment_status',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Compliance Report',
                'name' => 'appointment_reports_compliance_reports',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Appointment Rescheduled Count Report',
                'name' => 'appointment_reports_rescheduled_count_report',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],

        ]);

        $role = Role::findOrFail(1);

        // Assign Permission to 'administrator' role
        $role->givePermissionTo('appointment_reports_manage');
        $role->givePermissionTo('appointment_reports_general_report');
        $role->givePermissionTo('appointment_reports_staff_appointment');
        $role->givePermissionTo('appointment_reports_referred_by_staff_appointment');
        $role->givePermissionTo('appointment_reports_empolyee_summary');
        $role->givePermissionTo('appointment_reports_summary_by_service');
        $role->givePermissionTo('appointment_reports_summary_by_appointment_status');
        $role->givePermissionTo('appointment_reports_clients_by_appointment_status');
        $role->givePermissionTo('appointment_reports_general_summary_report');
        $role->givePermissionTo('appointment_reports_compliance_reports');
        $role->givePermissionTo('appointment_reports_rescheduled_count_report');

    }
}
