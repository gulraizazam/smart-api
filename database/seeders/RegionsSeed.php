<?php

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\Regions;

class RegionsSeed extends Seeder
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
            'title' => 'Regions',
            'name' => 'regions_manage',
            'guard_name' => 'web',
            'main_group' => 1,
            'parent_id' => 0,
        ]);
        Permission::insert([
            [
                'title' => 'Create',
                'name' => 'regions_create',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Edit',
                'name' => 'regions_edit',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Activate',
                'name' => 'regions_active',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Inactivate',
                'name' => 'regions_inactive',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Delete',
                'name' => 'regions_destroy',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Delete',
                'name' => 'regions_sort',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ]
        ]);

        $role = Role::findOrFail(1);

        // Assign Permission to 'administrator' role
        $role->givePermissionTo('regions_manage');
        $role->givePermissionTo('regions_create');
        $role->givePermissionTo('regions_edit');
        $role->givePermissionTo('regions_active');
        $role->givePermissionTo('regions_inactive');
        $role->givePermissionTo('regions_destroy');
        $role->givePermissionTo('regions_sort');

        Regions::insert([
            1 => array(
                'id' => 1,
                'name' => 'East Region',
                'sort_number'=>'2',
                'slug'=> 'custom',
                'account_id'=>'1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            2 => array(
                'id' => 2,
                'name' => 'West Region',
                'sort_number'=>'3',
                'slug'=> 'custom',
                'account_id'=>'1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            3 => array(
                'id' => 3,
                'name' => 'North Region',
                'sort_number'=>'4',
                'slug'=> 'custom',
                'account_id'=>'1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            4 => array(
                'id' => 4,
                'name' => 'South Region',
                'sort_number'=>'5',
                'slug'=> 'custom',
                'account_id'=>'1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            5 => array(
                'id' => 5,
                'name' => 'Central Region',
                'sort_number'=>'6',
                'slug'=> 'custom',
                'account_id'=>'1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            6 => array(
                'id' => 6,
                'name' => 'All Regions',
                'sort_number'=>'1',
                'slug'=> 'all',
                'account_id'=>'1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
        ]);

    }
}
