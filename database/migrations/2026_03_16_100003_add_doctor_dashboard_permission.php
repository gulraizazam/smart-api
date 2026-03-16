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
