<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateServiceHasLocationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('service_has_locations', function (Blueprint $table) {
            $table->unsignedBigInteger('service_id');
            $table->unsignedBigInteger('location_id');
            $table->unsignedBigInteger('account_id');

            // Manage Foreing Key Relationshops
            $table->foreign('location_id', 'servicehaslocations_locations')->references('id')->on('locations')->onDelete('cascade');
            $table->foreign('service_id', 'servicehaslocations_services')->references('id')->on('services')->onDelete('cascade');
            $table->foreign('account_id', 'servicehaslocations_accounts')->references('id')->on('accounts')->onDelete('cascade');

            $table->primary(['service_id', 'location_id', 'account_id'], 'servicehaslocations_mixed');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('service_has_locations');
    }
}
