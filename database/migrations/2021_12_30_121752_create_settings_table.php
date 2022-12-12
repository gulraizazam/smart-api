<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('name', 500);
            $table->text('data');
            $table->string('slug');
            $table->unsignedBigInteger('account_id')->nullable();
            $table->unsignedTinyInteger('active')->default(1);

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('account_id', 'settings_account')->references('id')->on('accounts');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('settings');
    }
}
