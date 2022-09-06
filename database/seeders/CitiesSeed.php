<?php

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\Cities;

class CitiesSeed extends Seeder
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
            'title' => 'Cities',
            'name' => 'cities_manage',
            'guard_name' => 'web',
            'main_group' => 1,
            'parent_id' => 0,
        ]);
        Permission::insert([
            [
                'title' => 'Create',
                'name' => 'cities_create',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Edit',
                'name' => 'cities_edit',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Activate',
                'name' => 'cities_active',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Inactivate',
                'name' => 'cities_inactive',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Delete',
                'name' => 'cities_destroy',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Sort',
                'name' => 'cities_sort',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ]
        ]);

        $role = Role::findOrFail(1);

        // Assign Permission to 'administrator' role
        $role->givePermissionTo('cities_manage');
        $role->givePermissionTo('cities_create');
        $role->givePermissionTo('cities_edit');
        $role->givePermissionTo('cities_active');
        $role->givePermissionTo('cities_inactive');
        $role->givePermissionTo('cities_destroy');
        $role->givePermissionTo('cities_sort');

        Cities::insert([
            1 => array(
                'id' => 1,
                'slug' => 'custom',
                'name' => 'Lahore',
                'sort_number'=>'2',
                'is_featured'=> 1,
                'region_id'=> 5,
                'account_id'=>'1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            2 => array(
                'id' => 2,
                'slug' => 'custom',
                'name' => 'Karachi',
                'sort_number'=>'3',
                'is_featured'=> 1,
                'region_id'=> 4,
                'account_id'=>'1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            3 => array(
                'id' => 3,
                'slug' => 'custom',
                'name' => 'Islamabad',
                'sort_number'=>'4',
                'is_featured'=> 1,
                'region_id'=> 3,
                'account_id'=>'1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            4 => array(
                'id' => 4,
                'slug' => 'custom',
                'name' => 'Peshawar',
                'sort_number'=>'5',
                'is_featured'=> 1,
                'region_id'=> 3,
                'account_id'=>'1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),

            5 => array(
                'id' => 5,
                'slug' => 'all',
                'name' => 'All Cities',
                'sort_number'=>'1',
                'is_featured'=> 0,
                'region_id'=> 6,
                'account_id'=>'1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            6 => array(
                'id' => 6,
                'slug' => 'region',
                'name' => 'All East Region',
                'sort_number' => '2',
                'is_featured' => 0,
                'region_id' => 1,
                'account_id' => '1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            7 => array(
                'id' => 7,
                'slug' => 'region',
                'name' => 'All West Region',
                'sort_number' => '3',
                'is_featured' => 0,
                'region_id' => 2,
                'account_id' => '1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            8 => array(
                'id' => 8,
                'slug' => 'region',
                'name' => 'All North Region',
                'sort_number' => '4',
                'is_featured' => 0,
                'region_id' => 3,
                'account_id' => '1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            9 => array(
                'id' => 9,
                'slug' => 'region',
                'name' => 'All South Region',
                'sort_number' => '5',
                'is_featured' => 0,
                'region_id' => 4,
                'account_id' => '1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            10 => array(
                'id' => 10,
                'slug' => 'region',
                'name' => 'All Central Region',
                'sort_number' => '1',
                'is_featured' => 0,
                'region_id' => 5,
                'account_id' => '1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
        ]);

    }
}
