<?php

use App\Models\Accounts;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //        // Permissions has been added
        //        $MainPermission = Permission::create([
        //            'title' => 'Accounts',
        //            'name' => 'accounts_manage',
        //            'guard_name' => 'web',
        //            'main_group' => 1,
        //            'parent_id' => 0,
        //        ]);
        //
        //        $role = Role::findOrFail(1);
        //
        //        // Assign Permission to 'administrator' role
        //        $role->givePermissionTo('accounts_manage');

        Accounts::insert([
            1 => [
                'id' => 1,
                'name' => 'Smart Aesthetics',
                'email' => 'care@smartaesthetics.pk',
                'contact' => '03403402222',
                'resource_person' => 'Smart Life',
                'suspended' => '0',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ],
        ]);

    }
}
