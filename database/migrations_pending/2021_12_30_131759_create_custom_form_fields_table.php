<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCustomFormFieldsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('custom_form_fields', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('field_type');
            $table->text('content')->nullable();
            $table->unsignedTinyInteger('active')->default(1);
            $table->unsignedBigInteger('sort_number')->nullable();
            $table->unsignedBigInteger('section_id')->default(1)->comment('this will be used when one want to create form with multiple sections');
            $table->unsignedBigInteger('user_form_id');
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_form_id')->references('id')->on('custom_forms');
            $table->foreign('account_id')->references('id')->on('accounts');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('custom_form_fields');
    }
}
