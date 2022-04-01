<?php

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CentreReportSeeder extends Seeder
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
            'title' => 'Centre Reports',
            'name' => 'centers_reports_manage',
            'guard_name' => 'web',
            'main_group' => 1,
            'parent_id' => 0,
        ]);
        Permission::insert([
            [
                'title' => 'Clients with completed treatments',
                'name' => 'centers_reports_client_with_Completed_treatment',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Clients with not completed treatments',
                'name' => 'centers_reports_client_with_not_Completed_treatment',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Clients with treatments in a particular month',
                'name' => 'centers_reports_clients_took_treatments_particular_month',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Clients with birthday + x days',
                'name' => 'centers_reports_clients_with_birthday_days',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
        ]);

        $role = Role::findOrFail(1);

        // Assign Permission to 'administrator' role
        $role->givePermissionTo('centers_reports_manage');
        $role->givePermissionTo('centers_reports_client_with_Completed_treatment');
        $role->givePermissionTo('centers_reports_client_with_not_Completed_treatment');
        $role->givePermissionTo('centers_reports_clients_took_treatments_particular_month');
        $role->givePermissionTo('centers_reports_clients_with_birthday_days');
    }
}
