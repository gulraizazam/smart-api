<?php

use App\Models\Locations;
use App\Models\ServiceHasLocations;
use App\Models\Services;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class LocationsSeed extends Seeder
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
            'title' => 'Centres',
            'name' => 'locations_manage',
            'guard_name' => 'web',
            'main_group' => 1,
            'parent_id' => 0,
        ]);
        Permission::insert([
            [
                'title' => 'Create',
                'name' => 'locations_create',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Edit',
                'name' => 'locations_edit',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Activate',
                'name' => 'locations_active',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Inactivate',
                'name' => 'locations_inactive',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Delete',
                'name' => 'locations_destroy',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Sort',
                'name' => 'locations_sort',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
        ]);

        $role = Role::findOrFail(1);

        // Assign Permission to 'administrator' role
        $role->givePermissionTo('locations_manage');
        $role->givePermissionTo('locations_create');
        $role->givePermissionTo('locations_edit');
        $role->givePermissionTo('locations_active');
        $role->givePermissionTo('locations_inactive');
        $role->givePermissionTo('locations_destroy');
        $role->givePermissionTo('locations_sort');

        $services = Services::where('parent_id', '!=', '0')->first();

        $locations = [
            [
                'slug' => 'custom',
                'name' => '3D Lifestyle Center of Medical Aesthetics',
                'fdo_name' => 'Shumaila Ashraf',
                'fdo_phone' => '3444458793',
                'address' => '49, E Block, Maulana Shaukat Ali Road Johar Town,Lahore',
                'google_map' => 'https://goo.gl/maps/UNQKTGDzdNo',
                'city_id' => 1,
                'region_id' => 5,
                'sort_no' => 1,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'account_id' => '1',
            ],

        ];

        foreach ($locations as $location) {
            $userObj = $location;
            if ($loc = Locations::create($userObj)) {
                ServiceHasLocations::create([
                    'location_id' => $loc->id,
                    'service_id' => $services->id,
                    'account_id' => $loc->account_id,
                ]);
            }
        }
    }
}
