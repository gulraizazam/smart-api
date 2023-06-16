<?php

use App\Models\CancellationReasons;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

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
            1 => [
                'id' => 1,
                'name' => 'Didn\'t Remember',
                'appointment_type_id' => '1',
                'account_id' => '1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ],
            2 => [
                'id' => 2,
                'name' => 'Not Attending Phone',
                'appointment_type_id' => '1',
                'account_id' => '1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ],
            3 => [
                'id' => 3,
                'name' => 'Not Interested',
                'appointment_type_id' => '1',
                'account_id' => '1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ],
            4 => [
                'id' => 4,
                'name' => 'Other Reason',
                'appointment_type_id' => '1',
                'account_id' => '1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ],
            5 => [
                'id' => 5,
                'name' => 'Didn\'t Remember',
                'appointment_type_id' => '2',
                'account_id' => '1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ],
            6 => [
                'id' => 6,
                'name' => 'Not Attending Phone',
                'appointment_type_id' => '2',
                'account_id' => '1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ],
            7 => [
                'id' => 7,
                'name' => 'Not Interested',
                'appointment_type_id' => '2',
                'account_id' => '1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ],
            8 => [
                'id' => 8,
                'name' => 'Other Reason',
                'appointment_type_id' => '2',
                'account_id' => '1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ],
        ]);

    }
}
