<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUsersDetailsToLeadsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('name')->index();
            $table->string('email')->nullable();
            $table->string('phone')->index();
            $table->tinyInteger('gender')->default(0);
            $table->unsignedInteger('referred_by');
            $table->foreign('referred_by')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('name')->index();
            $table->string('email')->nullable();
            $table->string('phone')->unique();
            $table->tinyInteger('gender')->default(0);
            $table->unsignedBigInteger('referred_by')->nullable();
        });
    }
}
