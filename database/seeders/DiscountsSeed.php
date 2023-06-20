<?php

use App\Models\Discounts;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DiscountsSeed extends Seeder
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
            'title' => 'Discounts',
            'name' => 'discounts_manage',
            'guard_name' => 'web',
            'main_group' => 1,
            'parent_id' => 0,
        ]);

        Permission::insert([
            [
                'title' => 'Create',
                'name' => 'discounts_create',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Edit',
                'name' => 'discounts_edit',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Activate',
                'name' => 'discounts_active',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Inactivate',
                'name' => 'discounts_inactive',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Delete',
                'name' => 'discounts_destroy',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Allocate',
                'name' => 'discounts_allocate',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
        ]);

        $role = Role::findOrFail(1);

        // Assign Permission to 'administrator' role
        $role->givePermissionTo('discounts_manage');
        $role->givePermissionTo('discounts_create');
        $role->givePermissionTo('discounts_edit');
        $role->givePermissionTo('discounts_active');
        $role->givePermissionTo('discounts_inactive');
        $role->givePermissionTo('discounts_destroy');
        $role->givePermissionTo('discounts_allocate');

        Discounts::insert([
            1 => [
                'id' => 1,
                'slug' => 'default',
                'name' => 'Facebook Promotion',
                'type' => 'Fixed',
                'amount' => 250,
                'start' => '2018-04-01 17:29:58',
                'end' => '2018-12-25 17:29:58',
                'account_id' => '1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ],
            2 => [
                'id' => 2,
                'slug' => 'default',
                'name' => 'Instragram Promotion',
                'type' => 'Fixed',
                'amount' => 350,
                'start' => '2018-04-01 17:29:58',
                'end' => '2018-12-25 17:29:58',
                'account_id' => '1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ],
            3 => [
                'id' => 3,
                'slug' => 'custom',
                'name' => 'Custom',
                'type' => 'Fixed',
                'amount' => 0,
                'start' => '2018-04-01 17:29:58',
                'end' => '2018-12-25 17:29:58',
                'account_id' => '1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ],
            4 => [
                'id' => 4,
                'slug' => 'birthday',
                'name' => 'Birthday Promotion',
                'type' => 'Percentage',
                'amount' => 25,
                'start' => '2018-04-01 17:29:58',
                'end' => '2018-12-25 17:29:58',
                'account_id' => '1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ],
        ]);
    }
}
