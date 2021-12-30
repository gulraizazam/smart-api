<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserOperatorSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_operator_settings', function (Blueprint $table) {
            $table->id();
            $table->string('operator_name');
            $table->string('username');
            $table->string('password');
            $table->string('mask')->nullable();
            $table->string('test_mode');
            $table->string('url')->nullable();
            $table->string('string_1')->nullable();
            $table->string('string_2')->nullable();

            $table->unsignedBigInteger('operator_id');
            $table->unsignedBigInteger('account_id');

            $table->foreign('operator_id', 'user_operator_settings_operator')->references('id')->on('global_operator_settings');
            $table->foreign('account_id', 'user_operator_settings_account')->references('id')->on('accounts');

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
        Schema::dropIfExists('user_operator_settings');
    }
}
