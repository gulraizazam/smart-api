<?php

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\Accounts;

class DashboardSeeder extends Seeder
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
            'title' => 'Dashboard',
            'name' => 'dashboard_manage',
            'guard_name' => 'web',
            'main_group' => 1,
            'parent_id' => 0,
        ]);
        Permission::insert([
            [
                'title' => 'Revenue by Centre',
                'name' => 'dashboard_revenue_by_centre',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Collection by Centre',
                'name' => 'dashboard_collection_by_centre',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'My Collection by Centre',
                'name' => 'dashboard_my_collection_by_centre',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Revenue by Service',
                'name' => 'dashboard_revenue_by_service',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'My Performance by Centre',
                'name' => 'dashboard_my_revenue_by_centre',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'My Performance by Service',
                'name' => 'dashboard_my_revenue_by_service',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Appointment by Status',
                'name' => 'dashboard_appointment_by_status',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Appointment by Type',
                'name' => 'dashboard_appointment_by_type',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'My Appointments Performance by Status',
                'name' => 'dashboard_my_appointment_by_status',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'My Appointments Performance by Type',
                'name' => 'dashboard_my_appointment_by_type',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ]
        ]);

        $role = Role::findOrFail(1);

        // Assign Permission to 'administrator' role
        $role->givePermissionTo('dashboard_manage');
        $role->givePermissionTo('dashboard_revenue_by_centre');
        $role->givePermissionTo('dashboard_collection_by_centre');
        $role->givePermissionTo('dashboard_my_collection_by_centre');
        $role->givePermissionTo('dashboard_revenue_by_service');
        $role->givePermissionTo('dashboard_my_revenue_by_centre');
        $role->givePermissionTo('dashboard_my_revenue_by_service');
        $role->givePermissionTo('dashboard_appointment_by_status');
        $role->givePermissionTo('dashboard_appointment_by_type');
        $role->givePermissionTo('dashboard_my_appointment_by_status');
        $role->givePermissionTo('dashboard_my_appointment_by_type');

    }
}
