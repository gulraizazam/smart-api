<?php

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class staffReportSeeder extends Seeder
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
            'title' => 'Staff Listing Reports',
            'name' => 'staff_listing_reports_manage',
            'guard_name' => 'web',
            'main_group' => 1,
            'parent_id' => 0,
        ]);
        Permission::insert([
            [
                'title' => 'Region Wise Staff List',
                'name' => 'staff_listing_reports_region_wise_staff_list',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Centre Wise Staff List',
                'name' => 'staff_listing_reports_centre_wise_staff_list',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
        ]);


        // Permissions has been added
        $MainPermission = Permission::create([
            'title' => 'Staff Revenue Reports',
            'name' => 'staff_revenue_reports_manage',
            'guard_name' => 'web',
            'main_group' => 1,
            'parent_id' => 0,
        ]);
        Permission::insert([
            [
                'title' => 'Staff Revenue Centre Wise',
                'name' => 'staff_revenue_reports_center_performance_stats_by_revenue',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Staff Revenue by Service Type',
                'name' => 'staff_revenue_reports_center_performance_stats_by_service_type',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
        ]);


        $role = Role::findOrFail(1);
        // Assign Permission to 'administrator' role
        $role->givePermissionTo('staff_listing_reports_manage');
        $role->givePermissionTo('staff_listing_reports_region_wise_staff_list');
        $role->givePermissionTo('staff_listing_reports_centre_wise_staff_list');
        $role->givePermissionTo('staff_revenue_reports_manage');
        $role->givePermissionTo('staff_revenue_reports_center_performance_stats_by_revenue');
        $role->givePermissionTo('staff_revenue_reports_center_performance_stats_by_service_type');
    }
}
