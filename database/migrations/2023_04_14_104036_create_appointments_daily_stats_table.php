<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAppointmentsDailyStatsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('appointments_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('centre_id');
            $table->foreign('centre_id')->references('id')->on('locations');
            $table->unsignedInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users');
            $table->unsignedInteger('appointment_id');
            $table->foreign('appointment_id')->references('id')->on('appointments');
            $table->unsignedInteger('appointment_status_id');
            $table->foreign('appointment_status_id')->references('id')->on('appointment_statuses');
            $table->date('cron_current_date');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('appointments_daily_stats');
    }
}
