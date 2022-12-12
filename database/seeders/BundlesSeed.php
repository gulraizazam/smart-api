<?php

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\Services;
use App\Models\Bundles;
use App\Models\BundleHasServices;
use App\Models\BundleServicesPriceHistory;

class BundlesSeed extends Seeder
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
            'title' => 'Packages',
            'name' => 'packages_manage',
            'guard_name' => 'web',
            'main_group' => 1,
            'parent_id' => 0,
        ]);
        Permission::insert([
            [
                'title' => 'Create',
                'name' => 'packages_create',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Edit',
                'name' => 'packages_edit',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Activate',
                'name' => 'packages_active',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Inactivate',
                'name' => 'packages_inactive',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Delete',
                'name' => 'packages_destroy',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
        ]);

        $role = Role::findOrFail(1);

        // Assign Permission to 'administrator' role
        $role->givePermissionTo('packages_manage');
        $role->givePermissionTo('packages_create');
        $role->givePermissionTo('packages_edit');
        $role->givePermissionTo('packages_active');
        $role->givePermissionTo('packages_inactive');
        $role->givePermissionTo('packages_destroy');


        $services = Services::all();

        if($services->count()) {
            // Create bundle from Services
            foreach($services as $service) {
                $bundle = Bundles::create(array(
                    'name' => $service->name,
                    'price' => $service->price,
                    'type' => 'single',
                    'total_services' => 1,
                    'account_id' => 1,
                ));
                BundleHasServices::create(array(
                    'bundle_id' => $bundle->id,
                    'service_id' => $service->id,
                    'service_price' => $service->price,
                    'calculated_price' => $service->price,
                    'end_node' => $service->end_node,
                ));
                BundleServicesPriceHistory::createRecord(array(
                    'bundle_id' => $bundle->id,
                    'bundle_price' => $service->price,
                    'bundle_services_price' => $service->price,
                    'service_id' => $service->id,
                    'service_price' => $service->price,
                    'effective_from' => \Carbon\Carbon::now()->format('Y-m-d'),
                    'created_by' => 1,
                    'updated_by' => 1,
                ), 1);
            }
        }

    }
}
