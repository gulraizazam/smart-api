<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddDoctorDashboardPermission extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Add doctor_dashboard permission
        $exists = DB::table('permissions')->where('name', 'doctor_dashboard')->first();
        if (!$exists) {
            DB::table('permissions')->insert([
                'name' => 'doctor_dashboard',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Assign permission to doctor roles
        $permissionId = DB::table('permissions')->where('name', 'doctor_dashboard')->value('id');
        $roleIds = DB::table('roles')
            ->whereIn('name', ['Aesthetic Doctor', 'Consultant', 'Lifestyle Consultant'])
            ->pluck('id')
            ->toArray();

        foreach ($roleIds as $roleId) {
            $alreadyAssigned = DB::table('role_has_permissions')
                ->where('permission_id', $permissionId)
                ->where('role_id', $roleId)
                ->exists();

            if (!$alreadyAssigned) {
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
        DB::table('permissions')->where('name', 'doctor_dashboard')->delete();
    }
}
