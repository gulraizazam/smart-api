<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBundlesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bundles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 500)->nullable();
            $table->double('price', 11, 2)->default(0.00);
            $table->double('services_price', 11, 2)->default(0.00);
            $table->unsignedBigInteger('total_services')->default(0);
            $table->unsignedTinyInteger('apply_discount')->default(1);
            $table->enum('type', ['single', 'multiple'])->default('multiple');
            $table->date('start')->nullable();
            $table->date('end')->nullable();

            $table->unsignedTinyInteger('active')->default(1);
            $table->unsignedBigInteger('tax_treatment_type_id');

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('account_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
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
        Schema::dropIfExists('bundles');
    }
}
