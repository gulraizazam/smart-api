<?php

use App\Models\AppointmentStatuses;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AppointmentStatusesSeed extends Seeder
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
            'title' => 'Appointment Statuses',
            'name' => 'appointment_statuses_manage',
            'guard_name' => 'web',
            'main_group' => 1,
            'parent_id' => 0,
        ]);
        Permission::insert([
            [
                'title' => 'Create',
                'name' => 'appointment_statuses_create',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Edit',
                'name' => 'appointment_statuses_edit',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Activate',
                'name' => 'appointment_statuses_active',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Inactivate',
                'name' => 'appointment_statuses_inactive',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Delete',
                'name' => 'appointment_statuses_destroy',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ]
        ]);

        $role = Role::findOrFail(1);

        // Assign Permission to 'administrator' role
        $role->givePermissionTo('appointment_statuses_manage');
        $role->givePermissionTo('appointment_statuses_create');
        $role->givePermissionTo('appointment_statuses_edit');
        $role->givePermissionTo('appointment_statuses_active');
        $role->givePermissionTo('appointment_statuses_inactive');
        $role->givePermissionTo('appointment_statuses_destroy');

        AppointmentStatuses::insert([
            1 => array(
                'id' => 1,
                'parent_id' => 0,
                'is_comment' => 0,
                'is_default' => 1,
                'is_arrived' => 0,
                'is_cancelled' => 0,
                'is_unscheduled' => 0,
                'allow_message' => 1,
                'sort_no' => 1,
                'name' => 'Pending',
                'appointment_type_id' => '1',
                'account_id' => '1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            2 => array(
                'id' => 2,
                'parent_id' => 0,
                'is_comment' => 0,
                'is_default' => 0,
                'is_arrived' => 1,
                'is_cancelled' => 0,
                'is_unscheduled' => 0,
                'allow_message' => 1,
                'sort_no' => 2,
                'name' => 'Arrived',
                'appointment_type_id' => '1',
                'account_id' => '1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            3 => array(
                'id' => 3,
                'parent_id' => 0,
                'is_comment' => 0,
                'is_default' => 0,
                'is_arrived' => 0,
                'is_cancelled' => 0,
                'is_unscheduled' => 0,
                'allow_message' => 0,
                'sort_no' => 3,
                'name' => 'No Show',
                'appointment_type_id' => '1',
                'account_id' => '1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            4 => array(
                'id' => 4,
                'parent_id' => 0,
                'is_comment' => 0,
                'is_default' => 0,
                'is_arrived' => 0,
                'is_cancelled' => 1,
                'is_unscheduled' => 0,
                'allow_message' => 0,
                'sort_no' => 4,
                'name' => 'Cancelled',
                'appointment_type_id' => '1',
                'account_id' => '1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            5 => array(
                'id' => 5,
                'name' => 'Didn\'t Remember',
                'parent_id' => 3,
                'is_comment' => 0,
                'is_default' => 0,
                'is_arrived' => 0,
                'is_cancelled' => 0,
                'is_unscheduled' => 0,
                'allow_message' => 0,
                'sort_no' => 5,
                'appointment_type_id' => '1',
                'account_id' => '1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            6 => array(
                'id' => 6,
                'name' => 'Not Attending Phone',
                'parent_id' => 3,
                'is_comment' => 0,
                'is_default' => 0,
                'is_arrived' => 0,
                'is_cancelled' => 0,
                'is_unscheduled' => 0,
                'allow_message' => 0,
                'sort_no' => 6,
                'appointment_type_id' => '1',
                'account_id' => '1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            7 => array(
                'id' => 7,
                'name' => 'Not Interested',
                'parent_id' => 3,
                'is_comment' => 0,
                'is_default' => 0,
                'is_arrived' => 0,
                'is_cancelled' => 0,
                'is_unscheduled' => 0,
                'allow_message' => 0,
                'sort_no' => 7,
                'appointment_type_id' => '1',
                'account_id' => '1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            8 => array(
                'id' => 8,
                'name' => 'Other Reason',
                'parent_id' => 3,
                'is_comment' => 1,
                'is_default' => 0,
                'is_arrived' => 0,
                'is_cancelled' => 0,
                'is_unscheduled' => 0,
                'allow_message' => 0,
                'sort_no' => 8,
                'appointment_type_id' => '1',
                'account_id' => '1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            9 => array(
                'id' => 9,
                'name' => 'Was Busy, couldn\'t make it',
                'parent_id' => 3,
                'is_comment' => 0,
                'is_default' => 0,
                'is_arrived' => 0,
                'is_cancelled' => 0,
                'is_unscheduled' => 0,
                'allow_message' => 0,
                'sort_no' => 9,
                'appointment_type_id' => '1',
                'account_id' => '1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            10 => array(
                'id' => 10,
                'name' => 'Too expensive',
                'parent_id' => 3,
                'is_comment' => 0,
                'is_default' => 0,
                'is_arrived' => 0,
                'is_cancelled' => 0,
                'is_unscheduled' => 0,
                'allow_message' => 0,
                'sort_no' => 10,
                'appointment_type_id' => '1',
                'account_id' => '1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            11 => array(
                'id' => 11,
                'name' => 'Un-Scheduled',
                'parent_id' => 0,
                'is_comment' => 0,
                'is_default' => 0,
                'is_arrived' => 0,
                'is_cancelled' => 0,
                'is_unscheduled' => 1,
                'allow_message' => 0,
                'sort_no' => 11,
                'appointment_type_id' => '1',
                'account_id' => '1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
        ]);
    }
}
