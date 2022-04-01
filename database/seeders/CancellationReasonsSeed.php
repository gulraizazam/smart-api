<?php

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\CancellationReasons;

class CancellationReasonsSeed extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {

//        // Permissions has been added
//        $MainPermission = Permission::create([
//            'title' => 'Cancellation Reasons',
//            'name' => 'cancellation_reasons_manage',
//            'guard_name' => 'web',
//            'main_group' => 1,
//            'parent_id' => 0,
//        ]);
//
//        $role = Role::findOrFail(1);
//        // Assign Permission to 'administrator' role
//        $role->givePermissionTo('cancellation_reasons_manage');

        CancellationReasons::insert([
            1 => array(
                'id' => 1,
                'name' => 'Didn\'t Remember',
                'appointment_type_id'=>'1',
                'account_id'=>'1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            2 => array(
                'id' => 2,
                'name' => 'Not Attending Phone',
                'appointment_type_id'=>'1',
                'account_id'=>'1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            3 => array(
                'id' => 3,
                'name' => 'Not Interested',
                'appointment_type_id'=>'1',
                'account_id'=>'1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            4 => array(
                'id' => 4,
                'name' => 'Other Reason',
                'appointment_type_id'=>'1',
                'account_id'=>'1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            5 => array(
                'id' => 5,
                'name' => 'Didn\'t Remember',
                'appointment_type_id'=>'2',
                'account_id'=>'1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            6 => array(
                'id' => 6,
                'name' => 'Not Attending Phone',
                'appointment_type_id'=>'2',
                'account_id'=>'1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            7 => array(
                'id' => 7,
                'name' => 'Not Interested',
                'appointment_type_id'=>'2',
                'account_id'=>'1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            8 => array(
                'id' => 8,
                'name' => 'Other Reason',
                'appointment_type_id'=>'2',
                'account_id'=>'1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
        ]);

    }
}
