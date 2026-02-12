<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBusinessClosuresTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('business_closures', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('account_id');
            $table->date('start_date');
            $table->date('end_date');
            $table->text('description')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('account_id')->references('id')->on('accounts');
            $table->foreign('created_by')->references('id')->on('users');
        });

        Schema::create('business_closure_locations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_closure_id');
            $table->unsignedInteger('location_id');
            $table->timestamps();

            $table->foreign('business_closure_id')->references('id')->on('business_closures')->onDelete('cascade');
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
        Schema::dropIfExists('business_closure_locations');
        Schema::dropIfExists('business_closures');
    }
}
