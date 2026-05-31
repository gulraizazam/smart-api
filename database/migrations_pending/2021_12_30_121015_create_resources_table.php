<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateResourcesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('resources', function (Blueprint $table) {
            $table->id();
            $table->String('name');

            $table->unsignedBigInteger('resource_type_id')->nullable();
            $table->unsignedBigInteger('location_id')->nullable();
            $table->unsignedBigInteger('external_id')->nullable();
            $table->unsignedBigInteger('machine_type_id')->nullable();
            $table->unsignedBigInteger('account_id')->nullable();

            $table->unsignedTinyInteger('active')->default(1);

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('machine_type_id', 'machine_type_resource_id')->references('id')->on('machine_types');
            $table->foreign('resource_type_id')->references('id')->on('resource_types');
            $table->foreign('location_id')->references('id')->on('locations');
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
        Schema::dropIfExists('resources');
    }
}
