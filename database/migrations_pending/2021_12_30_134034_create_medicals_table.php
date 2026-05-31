<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMedicalsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('medicals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('appointment_id');
            $table->unsignedBigInteger('custom_form_feedback_id');
            $table->date('date')->nullable();
            $table->timestamps();

            $table->foreign('patient_id')->references('id')->on('users');
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('appointment_id')->references('id')->on('appointments');
            $table->foreign('custom_form_feedback_id')->references('id')->on('custom_form_feedbacks');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('medicals');
    }
}
