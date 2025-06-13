<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAppointmentIdToActivitiesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('activities')) {
            Schema::table('activities', function (Blueprint $table) {
              if (!Schema::hasColumn('activities', 'appointment_id')) {
                $table->unsignedBigInteger('appointment_id')->nullable()->after('plan_id');
              }
            });
          }
       
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('activities')) {
            Schema::table('activities', function (Blueprint $table) {
              if (Schema::hasColumn('activities', 'appointment_id')) {
                $table->dropColumn('appointment_id');
              }
            });
          }
    }
}
