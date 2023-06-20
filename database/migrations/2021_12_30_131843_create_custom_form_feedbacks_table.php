<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCustomFormFeedbacksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('custom_form_feedbacks', function (Blueprint $table) {
            $table->id();
            $table->text('form_name')->nullable();
            $table->text('form_description')->nullable();
            $table->text('content')->nullable();
            $table->unsignedBigInteger('custom_form_id');
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('custom_form_type')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('custom_form_id')->references('id')->on('custom_forms');
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
        Schema::dropIfExists('custom_form_feedbacks');
    }
}
