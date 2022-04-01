<?php

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\AppointmentTypes;

class AppointmenttypesSeeder extends Seeder
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
//            'title' => 'Appointment Types',
//            'name' => 'appointment_types_manage',
//            'guard_name' => 'web',
//            'main_group' => 1,
//            'parent_id' => 0,
//        ]);
//
//        $role = Role::findOrFail(1);
//
//        // Assign Permission to 'administrator' role
//        $role->givePermissionTo('appointment_types_manage');

        AppointmentTypes::insert([
            1 => array(
                'id' => 1,
                'slug' => 'consultancy',
                'name' => 'Consultancy',
                'account_id'=>'1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            2 => array(
                'id' => 2,
                'slug' => 'treatment',
                'name' => 'Treatment',
                'account_id'=>'1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
        ]);
    }
}
