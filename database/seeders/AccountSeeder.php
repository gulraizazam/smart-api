<?php

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\Accounts;

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
            1 => array(
                'id' => 1,
                'name' => '3D Lifestyle',
                'email'=>' hello@3dlifestyle.pk',
                'contact'=>'03214466755',
                'resource_person'=>'3D Life',
                'suspended'=>'0',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
        ]);

    }
}
