<?php

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\Services;

class ServicesSeed extends Seeder
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
            'title' => 'Services',
            'name' => 'services_manage',
            'guard_name' => 'web',
            'main_group' => 1,
            'parent_id' => 0,
        ]);
        Permission::insert([
            [
                'title' => 'Create',
                'name' => 'services_create',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Edit',
                'name' => 'services_edit',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Activate',
                'name' => 'services_active',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Inactivate',
                'name' => 'services_inactive',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Delete',
                'name' => 'services_destroy',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ]
        ]);

        $role = Role::findOrFail(1);

        // Assign Permission to 'administrator' role
        $role->givePermissionTo('services_manage');
        $role->givePermissionTo('services_create');
        $role->givePermissionTo('services_edit');
        $role->givePermissionTo('services_active');
        $role->givePermissionTo('services_inactive');
        $role->givePermissionTo('services_destroy');

        Services::insert([
            1 => array(
                'id' => 1,
                'slug' => 'custom',
                'name' => 'Skin Tightening',
                'parent_id' => '0',
                'color' => '#8080ff',
                'duration' => '00:30',
                'end_node'=>'0',
                'price' => '0.00',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'account_id' => 1,
            ),
            2 => array(
                'id' => 2,
                'slug' => 'custom',
                'name' => 'Facial Rejuvenation',
                'parent_id' => '0',
                'color' => '#ff00ff',
                'duration' => '00:30',
                'end_node'=>'0',
                'price' => '0.00',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'account_id' => 1,
            ),
            3 => array(
                'id' => 3,
                'slug' => 'custom',
                'name' => 'Body Contouring',
                'parent_id' => '0',
                'color' => '#ff8080',
                'duration' => '00:30',
                'end_node'=>'0',
                'price' => '0.00',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'account_id' => 1,
            ),
            4 => array(
                'id' => 4,
                'slug' => 'custom',
                'name' => 'Trilogy ice',
                'parent_id' => '0',
                'color' => '#00ff00',
                'duration' => '00:30',
                'end_node'=>'0',
                'price' => '0.00',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'account_id' => 1,
            ),

            5 => array(
                'id' => 5,
                'slug' => 'custom',
                'name' => 'Face contouring',
                'parent_id' => '3',
                'color' => '#ff8080',
                'duration' => '01:30',
                'end_node'=>'1',
                'price' => '25000.00',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'account_id' => 1,
            ),
            6 => array(
                'id' => 6,
                'slug' => 'custom',
                'name' => 'Chin contouring',
                'parent_id' => '3',
                'color' => '#ff80ff',
                'duration' => '01:10',
                'end_node'=>'1',
                'price' => '25000.00',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'account_id' => 1,
            ),
            7 => array(
                'id' => 7,
                'slug' => 'custom',
                'name' => 'Chin Rejuvenation',
                'parent_id' => '2',
                'color' => '#ff00ff',
                'duration' => '01:10',
                'end_node'=>'1',
                'price' => '35000.00',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'account_id' => 1,
            ),
            8 => array(
                'id' => 8,
                'slug' => 'custom',
                'name' => 'Face Rejuvenation',
                'parent_id' => '2',
                'color' => '#0080ff',
                'duration' => '01:10',
                'end_node'=>'1',
                'price' => '35000.00',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'account_id' => 1,
            ),
            9 => array(
                'id' => 9,
                'slug' => 'custom',
                'name' => 'Face Skin Tightening',
                'parent_id' => '1',
                'color' => '#0080ff',
                'duration' => '01:10',
                'end_node'=>'1',
                'price' => '45000.00',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'account_id' => 1,
            ),
            10 => array(
                'id' => 10,
                'slug' => 'custom',
                'name' => 'Chin Skin Tightening',
                'parent_id' => '1',
                'color' => '#408080',
                'duration' => '01:10',
                'end_node'=>'1',
                'price' => '45000.00',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'account_id' => 1,
            ),
            11 => array(
                'id' => 11,
                'slug' => 'custom',
                'name' => 'Face Skin Trilogy',
                'parent_id' => '4',
                'color' => '#808080',
                'duration' => '01:10',
                'end_node'=>'1',
                'price' => '55000.00',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'account_id' => 1,
            ),
            12 => array(
                'id' => 12,
                'slug' => 'custom',
                'name' => 'Chin Skin Trilogy',
                'parent_id' => '4',
                'color' => '#808080',
                'duration' => '01:10',
                'end_node'=>'1',
                'price' => '55000.00',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'account_id' => 1,
            ),
            13 => array(
                'id' => 13,
                'slug' => 'all',
                'name' => 'All Services',
                'parent_id' => '0',
                'color' => '#808080',
                'duration' => '01:10',
                'end_node' => '1',
                'price' => '55000.00',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'account_id' => 1,
            ),
        ]);
    }
}
