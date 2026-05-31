<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAppointmentCommentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('appointment_comments', function (Blueprint $table) {
            $table->id();
            $table->text('comment')->nullable();

            $table->unsignedBigInteger('appointment_id');
            $table->unsignedBigInteger('created_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Manage Foreign Key Relationships Mapping
            $table->foreign('appointment_id')
                ->references('id')
                ->on('appointments');
            $table->foreign('created_by')
                ->references('id')
                ->on('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('appointment_comments');
    }
}
