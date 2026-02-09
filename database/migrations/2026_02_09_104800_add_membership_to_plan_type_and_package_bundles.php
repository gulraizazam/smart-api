<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddMembershipToPlanTypeAndPackageBundles extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 1. Alter plan_type enum on packages table to include 'membership'
        DB::statement("ALTER TABLE packages MODIFY COLUMN plan_type ENUM('plan', 'bundle', 'membership') DEFAULT 'plan'");

        // 2. Add membership_type_id and membership_code_id to package_bundles
        Schema::table('package_bundles', function (Blueprint $table) {
            $table->unsignedBigInteger('membership_type_id')->nullable()->after('bundle_id');
            $table->unsignedBigInteger('membership_code_id')->nullable()->after('membership_type_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Revert plan_type enum
        DB::statement("ALTER TABLE packages MODIFY COLUMN plan_type ENUM('plan', 'bundle') DEFAULT 'plan'");

        // Remove membership columns from package_bundles
        Schema::table('package_bundles', function (Blueprint $table) {
            $table->dropColumn(['membership_type_id', 'membership_code_id']);
        });
    }
}
