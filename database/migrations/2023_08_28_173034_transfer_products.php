<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class TransferProducts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transfer_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id');
            $table->integer('child_product_id');
            $table->integer('product_detail_id')->nullable();

            $table->unsignedInteger('from_location_id')->nullable();
            $table->foreignId('from_warehouse_id')->nullable();
            $table->unsignedInteger('to_location_id')->nullable();
            $table->foreignId('to_warehouse_id')->nullable();
            $table->integer('quantity');
            $table->date('transfer_date')->nullable();
            $table->unsignedInteger('account_id');
            $table->unsignedInteger('created_by');
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('account_id')->references('id')->on('accounts');
            $table->foreign('product_id')->references('id')->on('products');
            $table->foreign('from_location_id')->references('id')->on('locations');
            $table->foreign('from_warehouse_id')->references('id')->on('warehouses');
            $table->foreign('to_location_id')->references('id')->on('locations');
            $table->foreign('to_warehouse_id')->references('id')->on('warehouses');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transfer_products');
    }
}
