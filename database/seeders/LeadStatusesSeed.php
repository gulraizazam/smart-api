<?php

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\LeadStatuses;

class LeadStatusesSeed extends Seeder
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
            'title' => 'Lead Statuses',
            'name' => 'lead_statuses_manage',
            'guard_name' => 'web',
            'main_group' => 1,
            'parent_id' => 0,
        ]);
        Permission::insert([
            [
                'title' => 'Create',
                'name' => 'lead_statuses_create',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Edit',
                'name' => 'lead_statuses_edit',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Activate',
                'name' => 'lead_statuses_active',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Inactivate',
                'name' => 'lead_statuses_inactive',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Delete',
                'name' => 'lead_statuses_destroy',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Sort',
                'name' => 'lead_statuses_sort',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ]
        ]);

        $role = Role::findOrFail(1);

        // Assign Permission to 'administrator' role
        $role->givePermissionTo('lead_statuses_manage');
        $role->givePermissionTo('lead_statuses_create');
        $role->givePermissionTo('lead_statuses_edit');
        $role->givePermissionTo('lead_statuses_active');
        $role->givePermissionTo('lead_statuses_inactive');
        $role->givePermissionTo('lead_statuses_destroy');
        $role->givePermissionTo('lead_statuses_sort');

        LeadStatuses::insert([
            1 => array(
                'id' => 1,
                'parent_id'=> 0,
                'sort_no' => 1,
                'is_default' => 1,
                'is_arrived' => 0,
                'is_converted' => 0,
                'is_junk' => 0,
                'name' => 'Open',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'account_id'=>'1',
            ),
            2 => array(
                'id' => 2,
                'parent_id'=> 0,
                'sort_no' => 2,
                'is_default' => 0,
                'is_arrived' => 0,
                'is_converted' => 0,
                'is_junk' => 0,
                'name' => 'contacted Not Interested',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'account_id'=>'1',
            ),
            3 => array(
                'id' => 3,
                'parent_id'=> 0,
                'sort_no' => 3,
                'is_default' => 0,
                'is_arrived' => 0,
                'is_converted' => 0,
                'is_junk' => 0,
                'name' => 'Contacted Call Again',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'account_id'=>'1',
            ),
            4 => array(
                'id' => 4,
                'parent_id'=> 0,
                'sort_no' => 4,
                'is_default' => 0,
                'is_arrived' => 0,
                'is_converted' => 1,
                'is_junk' => 0,
                'name' => 'Contacted Booked',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'account_id'=>'1',
            ),
            5 => array(
                'id' => 5,
                'parent_id'=> 0,
                'sort_no' => 5,
                'name' => 'Junk',
                'is_default' => 0,
                'is_arrived' => 0,
                'is_converted' => 0,
                'is_junk' => 1,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'account_id'=>'1',
            ),
            6 => array(
                'id' => 6,
                'parent_id' => 0,
                'sort_no' => 6,
                'is_default' => 0,
                'is_arrived' => 1,
                'is_converted' => 0,
                'is_junk' => 0,
                'name' => 'Arrived',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'account_id' => '1',
            ),
        ]);
    }
}
