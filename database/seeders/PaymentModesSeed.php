<?php

use App\Models\PaymentModes;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PaymentModesSeed extends Seeder
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
            'title' => 'Payment Modes',
            'name' => 'payment_modes_manage',
            'guard_name' => 'web',
            'main_group' => 1,
            'parent_id' => 0,
        ]);

        Permission::insert([
            [
                'title' => 'Create',
                'name' => 'payment_modes_create',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Edit',
                'name' => 'payment_modes_edit',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Activate',
                'name' => 'payment_modes_active',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Inactivate',
                'name' => 'payment_modes_inactive',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Delete',
                'name' => 'payment_modes_destroy',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Sort',
                'name' => 'payment_modes_sort',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
        ]);

        $role = Role::findOrFail(1);

        // Assign Permission to 'administrator' role
        $role->givePermissionTo('payment_modes_manage');
        $role->givePermissionTo('payment_modes_create');
        $role->givePermissionTo('payment_modes_edit');
        $role->givePermissionTo('payment_modes_active');
        $role->givePermissionTo('payment_modes_inactive');
        $role->givePermissionTo('payment_modes_destroy');
        $role->givePermissionTo('payment_modes_sort');

        PaymentModes::insert([
            1 => [
                'id' => 1,
                'name' => 'Cash',
                'sort_number' => '1',
                'payment_type' => '1',
                'account_id' => '1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ],
            2 => [
                'id' => 2,
                'name' => 'Card',
                'sort_number' => '2',
                'payment_type' => '2',
                'account_id' => '1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ],
            3 => [
                'id' => 3,
                'name' => 'PayPal',
                'sort_number' => '3',
                'payment_type' => '3',
                'account_id' => '1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ],
            4 => [
                'id' => 4,
                'name' => 'Bank/Wire Transfer',
                'sort_number' => '4',
                'payment_type' => '4',
                'account_id' => '1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ],
        ]);

    }
}
