<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTreatmentIdToLeadsServicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('leads_services', function (Blueprint $table) {
            $table->unsignedInteger('treatment_id')->nullable();
            $table->foreign('treatment_id')->references('id')->on('appointments');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('leads_services', function (Blueprint $table) {
            $table->dropForeign(['treatment_id']);
            $table->dropColumn(['treatment_id']);
        });
    }
}
