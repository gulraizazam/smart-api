<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWarehousesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('account_id');
            $table->string('name');
            $table->string('manager_name', 500)->nullable();
            $table->string('manager_phone', 50)->nullable();
            $table->text('address')->nullable();
            $table->text('google_map')->nullable();
            $table->unsignedBigInteger('region_id');
            $table->unsignedBigInteger('city_id');
            $table->string('image_src')->nullable();
            $table->unsignedTinyInteger('active')->default(1);
            $table->timestamps();
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
        Schema::dropIfExists('warehosues');
    }
}
