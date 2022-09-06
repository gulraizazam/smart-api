<?php

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\LeadSources;

class LeadSourcesSeed extends Seeder
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
            'title' => 'Lead Sources',
            'name' => 'lead_sources_manage',
            'guard_name' => 'web',
            'main_group' => 1,
            'parent_id' => 0,
        ]);
        Permission::insert([
            [
                'title' => 'Create',
                'name' => 'lead_sources_create',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Edit',
                'name' => 'lead_sources_edit',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Activate',
                'name' => 'lead_sources_active',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Inactivate',
                'name' => 'lead_sources_inactive',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Delete',
                'name' => 'lead_sources_destroy',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Sort',
                'name' => 'lead_sources_sort',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ]
        ]);

        $role = Role::findOrFail(1);

        // Assign Permission to 'administrator' role
        $role->givePermissionTo('lead_sources_manage');
        $role->givePermissionTo('lead_sources_create');
        $role->givePermissionTo('lead_sources_edit');
        $role->givePermissionTo('lead_sources_active');
        $role->givePermissionTo('lead_sources_inactive');
        $role->givePermissionTo('lead_sources_destroy');
        $role->givePermissionTo('lead_sources_sort');

        LeadSources::insert([
            1 => array(
                'id' => 1,
                'sort_no' => 1,
                'name' => 'Website',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'account_id'=>'1',
            ),
            2 => array(
                'id' => 2,
                'sort_no' => 2,
                'name' => 'Social Media',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'account_id'=>'1',
            ),
            3 => array(
                'id' => 3,
                'sort_no' => 3,
                'name' => 'Referral',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'account_id'=>'1',
            ),
            4 => array(
                'id' => 4,
                'sort_no' => 4,
                'name' => 'Newspaper/Magazine',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'account_id'=>'1',
            ),
            4 => array(
                'id' => 5,
                'sort_no' => 5,
                'name' => 'Other',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'account_id'=>'1',
            ),
        ]);
    }
}
