<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsConvertedToAppointmentStatusesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('appointment_statuses', function (Blueprint $table) {
            $table->tinyInteger('is_converted')->default(0)->after('is_arrived');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('appointment_statuses', function (Blueprint $table) {
            $table->dropColumn('is_converted');
        });
    }
}
