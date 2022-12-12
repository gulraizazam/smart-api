<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateResourceHasRotaTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('resource_has_rota', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('region_id')->nullable();
            $table->unsignedBigInteger('city_id')->nullable();
            $table->unsignedBigInteger('location_id')->nullable();
            $table->date('start');
            $table->date('end');
            $table->string('monday')->nullable();
            $table->string('tuesday')->nullable();
            $table->string('wednesday')->nullable();
            $table->string('thursday')->nullable();
            $table->string('friday')->nullable();
            $table->string('saturday')->nullable();
            $table->string('sunday')->nullable();
            $table->string('monday_off')->nullable();
            $table->string('tuesday_off')->nullable();
            $table->string('wednesday_off')->nullable();
            $table->string('thursday_off')->nullable();
            $table->string('friday_off')->nullable();
            $table->string('saturday_off')->nullable();
            $table->string('sunday_off')->nullable();
            $table->unsignedTinyInteger('copy_all')->nullable();
            $table->unsignedTinyInteger('active')->default(1);
            $table->unsignedTinyInteger('is_consultancy')->default(1);
            $table->unsignedTinyInteger('is_treatment')->default(1);
            $table->unsignedBigInteger('resource_type_id')->nullable();
            $table->unsignedBigInteger('account_id')->nullable();
            $table->unsignedBigInteger('resource_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('resource_id', 'resource_has_rota_resource')->references('id')->on('resources');
            $table->foreign('region_id')->references('id')->on('regions');
            $table->foreign('city_id')->references('id')->on('cities');
            $table->foreign('location_id')->references('id')->on('locations');
            $table->foreign('resource_type_id')->references('id')->on('resource_types');
            $table->foreign('account_id')->references('id')->on('accounts');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('resource_has_rota');
    }
}
