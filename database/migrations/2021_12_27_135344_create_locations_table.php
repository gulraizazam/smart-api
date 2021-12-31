<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLocationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 40)->default('custom');
            $table->string('name', 500);
            $table->string('fdo_name', 500)->nullable();
            $table->string('fdo_phone', 50)->nullable();
            $table->text('address')->nullable();
            $table->text('google_map')->nullable();
            $table->unsignedBigInteger('region_id');
            $table->unsignedBigInteger('city_id');
            $table->string('ntn')->nullable();
            $table->string('stn')->nullable();
            $table->string('image_src')->nullable();
            $table->string('tax_percentage')->nullable();
            $table->unsignedTinyInteger('active')->default(1);
            $table->unsignedBigInteger('sort_no')->nullable();
            $table->unsignedBigInteger('account_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('region_id')
                ->references('id')
                ->on('regions');
            $table->foreign('city_id')
                ->references('id')
                ->on('cities');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('locations');
    }
}
