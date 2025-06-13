<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGetDiscountServicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('get_discount_services', function (Blueprint $table) {
            $table->id();
            $table->integer('discount_id');
            $table->string('service_price');
            $table->integer('sessions');
            $table->unsignedBigInteger('service_id');
            $table->unsignedBigInteger('base_service_id');
            $table->string('discount_type');
            $table->string('discount_amount');
            $table->timestamps();
    
            // Manage Foreign Key Relationships
            $table->foreign('discount_id')
                ->references('id')
                ->on('discounts')
                ->onDelete('cascade');

            $table->foreign('service_id')
                ->references('id')
                ->on('services')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('get_discount_services');
    }
}
