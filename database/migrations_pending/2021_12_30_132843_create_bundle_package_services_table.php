<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBundlePackageServicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bundle_package_services', function (Blueprint $table) {
            $table->id();
            $table->String('random_id')->nullable();
            $table->unsignedBigInteger('package_id')->nullable();
            $table->unsignedBigInteger('package_bundle_id')->nullable();
            $table->unsignedBigInteger('service_id')->nullable();
            $table->unsignedTinyInteger('is_consumed')->default(0);
            $table->double('orignal_price', 11, 2)->default(0.00);
            $table->double('price', 11, 2)->default(0.00);

            $table->unsignedTinyInteger('is_exclusive')->nullable();
            $table->double('tax_exclusive_price', 11, 2)->default(0.00);
            $table->double('tax_percenatage', 11, 2)->default(0.00);
            $table->double('tax_price', 11, 2)->default(0.00);
            $table->double('tax_including_price', 11, 2)->default(0.00);

            $table->timestamps();

            $table->foreign('package_id')->references('id')->on('packages');
            $table->foreign('package_bundle_id')->references('id')->on('package_bundles');
            $table->foreign('service_id')->references('id')->on('services');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('bundle_package_services');
    }
}
