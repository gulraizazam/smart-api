<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrderDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('order_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id');
            $table->foreignId('product_id');
            $table->foreignId('discount_id')->nullable();
            $table->integer('quantity');
            $table->float('sale_price', 8, 2);
            $table->float('discount_price', 8, 2)->nullable();
            $table->float('sale_price_after_discount', 8, 2);
            $table->enum('order_type', ['sale', 'refund', 'in_house_use']);
            $table->text('reason')->nullable();
            $table->unsignedInteger('account_id');
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders');
            $table->foreign('product_id')->references('id')->on('products');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('order_details');
    }
}
