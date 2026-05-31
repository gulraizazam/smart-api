<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHeavyLiftersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('heavy_lifters', function (Blueprint $table) {
            $table->id();
            $table->longText('payload');
            $table->string('type')->default('sync-appointments');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->dateTime('available_at');

            $table->unsignedBigInteger('account_id')->nullable();
            $table->foreign('account_id', 'heavy_lifters_account')->references('id')->on('accounts');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('heavy_lifters');
    }
}
