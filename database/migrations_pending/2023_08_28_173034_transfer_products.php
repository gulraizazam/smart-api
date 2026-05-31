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
            $table->unsignedBigInteger('child_product_id');
            $table->unsignedBigInteger('product_detail_id')->nullable();

            $table->unsignedBigInteger('from_location_id')->nullable();
            $table->foreignId('from_warehouse_id')->nullable();
            $table->unsignedBigInteger('to_location_id')->nullable();
            $table->foreignId('to_warehouse_id')->nullable();
            $table->integer('quantity');
            $table->date('transfer_date')->nullable();
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by')->nullable();
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
