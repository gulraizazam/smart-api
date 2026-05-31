<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDiscountsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('discounts', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 40)->default('default');
            $table->String('name')->nullable();
            $table->enum('type', ['Fixed', 'Percentage']);
            $table->double('amount', 11, 2)->nullabale();
            $table->String('description')->nullable();
            $table->string('discount_type')->nullable();
            $table->integer('pre_days')->default(0);
            $table->integer('post_days')->default(0);
            $table->date('start')->nullable();
            $table->date('end')->nullable();
            $table->unsignedTinyInteger('active')->default(1);

            $table->unsignedBigInteger('account_id')->nullable();

            // Foreign Key Relationships
            $table->foreign('account_id')->references('id')->on('accounts');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('discounts');
    }
}
