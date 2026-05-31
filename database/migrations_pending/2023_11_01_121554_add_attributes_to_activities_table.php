<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAttributesToActivitiesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Production note: this migration assumed an older `activities` table
        // shape with camelCase columns (`planId`, `patient`, `location`, ...)
        // that pre-dated the snake_case rename. On a fresh DB the create
        // migration already creates `plan_id` (and the camelCase columns
        // never existed), so we (a) skip columns that already exist and
        // (b) drop the `after()` clauses that reference non-existent siblings.
        Schema::table('activities', function (Blueprint $table) {
            if (! Schema::hasColumn('activities', 'plan_id')) {
                $table->unsignedBigInteger('plan_id')->nullable();
            }
            if (! Schema::hasColumn('activities', 'service_id')) {
                $table->unsignedBigInteger('service_id')->nullable();
            }
            if (! Schema::hasColumn('activities', 'activity_type')) {
                $table->string('activity_type')->nullable();
            }
            if (! Schema::hasColumn('activities', 'patient_id')) {
                $table->unsignedBigInteger('patient_id')->nullable();
            }
            if (! Schema::hasColumn('activities', 'centre_id')) {
                $table->unsignedBigInteger('centre_id')->nullable();
            }
            if (! Schema::hasColumn('activities', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn('plan_id');
            $table->dropColumn('service_id');
            $table->dropColumn('activity_type');
            $table->dropColumn('patient_id');
            $table->dropColumn('centre_id');
            $table->dropColumn('user_id');
        });
    }
}
