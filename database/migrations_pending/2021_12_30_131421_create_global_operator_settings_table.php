<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGlobalOperatorSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('global_operator_settings', function (Blueprint $table) {
            $table->id();
            $table->string('operator_name');
            $table->string('username');
            $table->string('password');
            $table->string('mask');
            $table->string('test_mode');
            $table->string('url');
            $table->string('string_1');
            $table->string('string_2');
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
        Schema::dropIfExists('global_operator_settings');
    }
}
