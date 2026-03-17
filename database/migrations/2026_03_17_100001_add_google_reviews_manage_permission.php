<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AddGoogleReviewsManagePermission extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        // Create the permission if it doesn't exist
        $permission = Permission::firstOrCreate(
            ['name' => 'google_reviews_manage', 'guard_name' => 'web']
        );

        // Assign to the same roles that have centre_targets_manage
        $roles = ['Administrator', 'Finance', 'Super-Admin'];
        foreach ($roles as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role && !$role->hasPermissionTo('google_reviews_manage')) {
                $role->givePermissionTo($permission);
            }
        }

        // Clear permission cache
        app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        $permission = Permission::where('name', 'google_reviews_manage')->first();
        if ($permission) {
            $permission->delete();
        }

        app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
