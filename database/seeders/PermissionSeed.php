<?php

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use \Spatie\Permission\Models\Role;

class PermissionSeed extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $role = Role::create([
            'name' => 'administrator',
            'commission' => 10
        ]);

        // Permissions has been added
        $MainPermission = Permission::create([
            'title' => 'Permissions',
            'name' => 'permissions_manage',
            'guard_name' => 'web',
            'main_group' => 1,
            'parent_id' => 0,
        ]);
        Permission::insert([
            [
                'title' => 'Create',
                'name' => 'permissions_create',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Edit',
                'name' => 'permissions_edit',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
//            [
//                'title' => 'Activate',
//                'name' => 'permissions_active',
//                'guard_name' => 'web',
//                'main_group' => 0,
//                'created_at' => \Carbon\Carbon::now(),
//                'updated_at' => \Carbon\Carbon::now(),
//                'parent_id' => $MainPermission->id,
//            ],
//            [
//                'title' => 'Inactivate',
//                'name' => 'permissions_inactive',
//                'guard_name' => 'web',
//                'main_group' => 0,
//                'created_at' => \Carbon\Carbon::now(),
//                'updated_at' => \Carbon\Carbon::now(),
//                'parent_id' => $MainPermission->id,
//            ],
            [
                'title' => 'Delete',
                'name' => 'permissions_destroy',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ]
        ]);

        $role = Role::findOrFail(1);

        // Assign Permission to 'administrator' role
        $role->givePermissionTo('permissions_manage');
        $role->givePermissionTo('permissions_create');
        $role->givePermissionTo('permissions_edit');
//        $role->givePermissionTo('permissions_active');
//        $role->givePermissionTo('permissions_inactive');
        $role->givePermissionTo('permissions_destroy');
    }
}
