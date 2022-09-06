<?php

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\UserTypes;

class UserTypesSeeder extends Seeder
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
            'title' => 'User Types',
            'name' => 'user_types_manage',
            'guard_name' => 'web',
            'main_group' => 1,
            'parent_id' => 0,
        ]);
        Permission::insert([
            [
                'title' => 'Edit',
                'name' => 'user_types_edit',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ]
        ]);

        $role = Role::findOrFail(1);

        // Assign Permission to 'administrator' role
        $role->givePermissionTo('user_types_manage');
        $role->givePermissionTo('user_types_edit');

        UserTypes::insert([
            1 => array(
                'id' => 1,
                'name' => 'Administrator',
                'type'=>'application user',
                'account_id'=>'1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            2 => array(
                'id' => 2,
                'name' => 'Application User',
                'type'=>'application user',
                'account_id'=>'1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
             3=> array(
                'id' => 3,
                'name' => 'Patient',
                 'type'=>'patient',
                 'account_id'=>'1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            5 => array(
                'id' => 5,
                'name' => 'Practitioner',
                'type'=>'practitioner',
                'account_id'=>'1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
        ]);
    }
}
