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
            $table->foreign('lead_id')->references('id')->on('leads');
            $table->unsignedInteger('service_id');
            $table->foreign('service_id')->references('id')->on('services');
            $table->unsignedInteger('child_service_id')->nullable();
            $table->foreign('child_service_id')->references('id')->on('services');
            $table->unsignedInteger('consultancy_id')->nullable();
            $table->foreign('consultancy_id')->references('id')->on('appointments');
            $table->integer('status')->default(0);
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
        Schema::dropIfExists('leads_services');
    }
}
