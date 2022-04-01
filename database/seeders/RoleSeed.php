<?php

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSeed extends Seeder
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
            'title' => 'Roles',
            'name' => 'roles_manage',
            'guard_name' => 'web',
            'main_group' => 1,
            'parent_id' => 0,
        ]);
        Permission::insert([
            [
                'title' => 'Create',
                'name' => 'roles_create',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Edit',
                'name' => 'roles_edit',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
//            [
//                'title' => 'Activate',
//                'name' => 'roles_active',
//                'guard_name' => 'web',
//                'main_group' => 0,
//                'created_at' => \Carbon\Carbon::now(),
//                'updated_at' => \Carbon\Carbon::now(),
//                'parent_id' => $MainPermission->id,
//            ],
//            [
//                'title' => 'Inactivate',
//                'name' => 'roles_inactive',
//                'guard_name' => 'web',
//                'main_group' => 0,
//                'created_at' => \Carbon\Carbon::now(),
//                'updated_at' => \Carbon\Carbon::now(),
//                'parent_id' => $MainPermission->id,
//            ],
            [
                'title' => 'Delete',
                'name' => 'roles_destroy',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Delete Bulk',
                'name' => 'roles_destroy_bulk',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ]
        ]);

        $role = Role::findOrFail(1);
        // Assign Permission to 'administrator' role
        $role->givePermissionTo('roles_manage');
        $role->givePermissionTo('roles_create');
        $role->givePermissionTo('roles_edit');
//        $role->givePermissionTo('roles_active');
//        $role->givePermissionTo('roles_inactive');
        $role->givePermissionTo('roles_destroy');
        $role->givePermissionTo('roles_destroy_bulk');
    }
}
