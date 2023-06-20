<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBundleHasServicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bundle_has_services', function (Blueprint $table) {
            $table->unsignedBigInteger('bundle_id');
            $table->unsignedBigInteger('service_id');
            $table->double('service_price', 11, 2)->default(0.00);
            $table->double('calculated_price', 11, 2)->default(0.00);
            $table->unsignedTinyInteger('end_node')->default(1);

            // Manage Foreign Key Relationships
            $table->foreign('service_id')
                ->references('id')
                ->on('services')
                ->onDelete('cascade');
            $table->foreign('bundle_id')
                ->references('id')
                ->on('bundles')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('bundle_has_services');
    }
}
