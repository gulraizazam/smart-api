<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AssignDoctorDashboardPermissionToRoles extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $permissionId = DB::table('permissions')->where('name', 'doctor_dashboard')->value('id');

        if (!$permissionId) {
            return;
        }

        $roleIds = DB::table('roles')
            ->whereIn('name', ['Aesthetic Doctor', 'Consultant', 'Lifestyle Consultant'])
            ->pluck('id')
            ->toArray();

        foreach ($roleIds as $roleId) {
            $exists = DB::table('role_has_permissions')
                ->where('permission_id', $permissionId)
                ->where('role_id', $roleId)
                ->exists();

            if (!$exists) {
                DB::table('role_has_permissions')->insert([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $permissionId = DB::table('permissions')->where('name', 'doctor_dashboard')->value('id');

        if (!$permissionId) {
            return;
        }

        $roleIds = DB::table('roles')
            ->whereIn('name', ['Aesthetic Doctor', 'Consultant', 'Lifestyle Consultant'])
            ->pluck('id')
            ->toArray();

        DB::table('role_has_permissions')
            ->where('permission_id', $permissionId)
            ->whereIn('role_id', $roleIds)
            ->delete();
    }
}
