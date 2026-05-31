<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRelationsForUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('user_type_id', 'users_user_type')->references('id')->on('user_types');
            $table->foreign('resource_type_id', 'users_resource_type')->references('id')->on('resource_types');
            $table->foreign('account_id', 'users_account')->references('id')->on('accounts');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('user_type_id', 'users_user_type')->references('id')->on('user_types');
            $table->foreign('resource_type_id', 'users_resource_type')->references('id')->on('resource_types');
            $table->foreign('account_id', 'users_account')->references('id')->on('accounts');
        });
    }
}
