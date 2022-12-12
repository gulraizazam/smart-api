<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAppointmentimagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('appointmentimages', function (Blueprint $table) {
            $table->id();
            $table->string('image_name')->nullable();
            $table->string('image_path')->nullable();
            $table->enum('type', ['Before Appointment', 'After Appointment']);
            $table->unsignedBigInteger('appointment_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('appointment_id')->references('id')->on('appointments');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('appointmentimages');
    }
}
