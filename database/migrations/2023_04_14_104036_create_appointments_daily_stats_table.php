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
            $table->integer('consultation_scheduled_count')->nullable();
            $table->integer('consultation_arrived_count')->nullable();
            $table->integer('treatment_scheduled_count')->nullable();
            $table->integer('treatment_arrived_count')->nullable();
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
