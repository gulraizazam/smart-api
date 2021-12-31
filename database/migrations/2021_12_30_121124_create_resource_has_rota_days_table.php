<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateResourceHasRotaDaysTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('resource_has_rota_days', function (Blueprint $table) {
            $table->id();
            $table->date('date')->nullable();
            $table->string('start_time')->nullable();
            $table->string('end_time')->nullable();
            $table->string('start_off')->nullable();
            $table->string('end_off')->nullable();
            $table->dateTime('start_timestamp')->nullable();
            $table->dateTime('end_timestamp')->nullable();
            $table->unsignedTinyInteger('active')->default(1);
            $table->unsignedBigInteger('resource_has_rota_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('resource_has_rota_id')->references('id')->on('resource_has_rota');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('resource_has_rota_days');
    }
}
