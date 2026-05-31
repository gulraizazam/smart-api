<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePackagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->String('random_id')->nullable();
            $table->String('name')->nullable();
            $table->String('sessioncount');
            $table->double('total_price', 11, 2)->default(0);
            $table->double('total_price_bk', 11, 2)->default(0);
            $table->unsignedBigInteger('is_refund')->default(0);
            $table->unsignedTinyInteger('is_exclusive')->nullable();

            $table->unsignedBigInteger('account_id')->nullable();
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->unsignedBigInteger('location_id')->nullable();
            $table->unsignedBigInteger('appointment_id')->nullable();

            $table->unsignedTinyInteger('active')->default(1);

            $table->timestamps();
            $table->softDeletes();

            /*Manage foreign Keys relationship*/
            $table->foreign('appointment_id', 'package_appointment_id')->references('id')->on('appointments');
            $table->foreign('account_id')->references('id')->on('accounts');
            $table->foreign('patient_id')->references('id')->on('users');
            $table->foreign('location_id')->references('id')->on('locations');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('packages');
    }
}
