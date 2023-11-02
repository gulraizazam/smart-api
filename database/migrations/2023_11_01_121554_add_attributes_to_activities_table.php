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
        Schema::table('activities', function (Blueprint $table) {
            $table->unsignedBigInteger('plan_id')->nullable()->after('planId');
            $table->unsignedBigInteger('service_id')->nullable()->after('service');
            $table->string('activity_type')->nullable()->after('appointment_type');
            $table->unsignedBigInteger('patient_id')->nullable()->after('patient');
            $table->unsignedBigInteger('centre_id')->nullable()->after('location');
            $table->unsignedBigInteger('user_id')->nullable()->after('created_by');
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
