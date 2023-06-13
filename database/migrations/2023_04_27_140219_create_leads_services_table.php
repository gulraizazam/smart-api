<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLeadsServicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('leads_services', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('lead_id');
            $table->unsignedInteger('service_id');
            $table->unsignedInteger('child_service_id')->nullable();
            $table->unsignedInteger('consultancy_id')->nullable();
            $table->integer('status')->default(0);
            $table->timestamps();

            $table->foreign('lead_id')->references('id')->on('leads');
            $table->foreign('service_id')->references('id')->on('services');
            $table->foreign('child_service_id')->references('id')->on('services');
            $table->foreign('consultancy_id')->references('id')->on('appointments');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('leads_services');
    }
}
