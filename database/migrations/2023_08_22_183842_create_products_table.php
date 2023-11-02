<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('account_id');
            $table->foreignId('brand_id');
            $table->unsignedInteger('location_id')->nullable();
            $table->foreignId('warehouse_id')->nullable();
            $table->float('sale_price', 8, 2)->nullable();
            $table->enum('product_type', ['in_house_use', 'for_sale']);
            $table->bigInteger('parent_id')->default(0);
            $table->tinyInteger('status')->default(1);
            $table->unsignedInteger('created_by');
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamps();


            $table->foreign('account_id')->references('id')->on('accounts');
            $table->foreign('brand_id')->references('id')->on('brands');
            $table->foreign('location_id')->references('id')->on('locations');
            $table->foreign('warehouse_id')->references('id')->on('warehouses');
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
        Schema::dropIfExists('products');
    }
}
