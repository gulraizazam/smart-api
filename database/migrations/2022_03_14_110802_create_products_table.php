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
            $table->unsignedInteger('account_id');
            $table->foreignId('brand_id');
            $table->string('name');
            $table->float('sale_price', 8, 2);
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
            $table->foreign('account_id')->references('id')->on('accounts');
            $table->foreign('brand_id')->references('id')->on('brands');

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
