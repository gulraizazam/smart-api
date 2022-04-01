<?php

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\UserOperatorSettings;
use Illuminate\Support\Facades\Config;

class UserOperatorSettingsSeed extends Seeder
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
            'title' => 'User Operator Settings',
            'name' => 'user_operator_settings_manage',
            'guard_name' => 'web',
            'main_group' => 1,
            'parent_id' => 0,
        ]);

        Permission::insert([
            [
                'title' => 'Edit',
                'name' => 'user_operator_settings_edit',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
        ]);

        $role = Role::findOrFail(1);

        // Assign Permission to 'administrator' role
        $role->givePermissionTo('user_operator_settings_manage');
        $role->givePermissionTo('user_operator_settings_edit');


        UserOperatorSettings::insert(Config::get('organization_setup_data.user_operator_settings'));
    }
}
