<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFeedbackTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('feedback', function (Blueprint $table) {
            $table->id();
            $table->string('patient_name')->nullable();
            $table->string('patient_phone')->nullable();
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->unsignedBigInteger('doctor_id')->nullable();
            $table->unsignedBigInteger('appointment_id')->nullable();
            $table->unsignedBigInteger('service_id')->nullable();
            $table->unsignedBigInteger('location_id')->nullable();
            $table->string('rating')->nullable();
            $table->text('comment')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            
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
        Schema::dropIfExists('feedback');
    }
}
