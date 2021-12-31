<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePackageBundlesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('package_bundles', function (Blueprint $table) {
            $table->id();
            $table->String('random_id')->nullable();
            $table->unsignedBigInteger('is_allocate')->default(0);
            $table->tinyInteger('qty');
            $table->string('discount_name')->nullable();
            $table->String('discount_type')->nullable();
            $table->double('discount_price', 11, 2)->nullable();
            $table->double('service_price', 11, 2);
            $table->double('net_amount', 11, 2);

            $table->unsignedBigInteger('discount_id')->nullable();
            $table->unsignedBigInteger('bundle_id')->nullable();
            $table->unsignedBigInteger('package_id')->nullable();
            $table->unsignedTinyInteger('active')->default(1);

            $table->double('orignal_price', 11, 2)->default(0.00);

            $table->unsignedTinyInteger('is_exclusive')->nullable();
            $table->double('tax_exclusive_net_amount', 11, 2)->default(0.00);
            $table->double('tax_percenatage', 11, 2)->default(0.00);
            $table->double('tax_price', 11, 2)->default(0.00);
            $table->double('tax_including_price', 11, 2)->default(0.00);
            $table->unsignedBigInteger('location_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            /*Manage foreign Keys relationship*/
            $table->foreign('location_id', 'package_bundle_location_id')->references('id')->on('locations');
            $table->foreign('bundle_id', 'package_bundles_bundle_id')->references('id')->on('bundles');
            $table->foreign('discount_id')->references('id')->on('discounts');
            $table->foreign('package_id')->references('id')->on('packages');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('package_bundles');
    }
}
