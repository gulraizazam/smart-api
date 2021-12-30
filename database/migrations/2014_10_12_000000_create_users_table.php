<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            $table->string('email')->nullable();
            $table->string('password');
            $table->string('phone')->index();
            $table->tinyInteger('main_account')->default(0);
            $table->tinyInteger('gender')->default(0);
            $table->string('cnic', 15)->nullable();
            $table->date('dob')->nullable();
            $table->double('commission', 11, 2)->default(0.00)->default(0);
            $table->unsignedBigInteger('user_type_id')->nullable();
            $table->unsignedBigInteger('resource_type_id')->nullable();
            $table->unsignedBigInteger('account_id')->nullable();
            $table->unsignedBigInteger('referred_by')->nullable();
            $table->text('address')->nullable();
            $table->string('remember_token')->nullable();
            $table->string('image_src')->nullable();
            $table->unsignedTinyInteger('active')->default(1);
            $table->tinyInteger('is_dr_migrated')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['name', 'phone']);
            $table->unique(['email', 'deleted_at']);

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users');
    }
}
