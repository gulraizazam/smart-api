<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAppointmentStatusesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('appointment_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('parent_id', 500);
            $table->string('name', 500);
            $table->string('is_comment', 500)->nullable();
            $table->tinyInteger('allow_message')->default(0);
            $table->tinyInteger('is_default')->default(0);
            $table->unsignedTinyInteger('is_arrived')->default(0);
            $table->unsignedTinyInteger('is_cancelled')->default(0);
            $table->unsignedTinyInteger('is_unscheduled')->default(0);
            $table->unsignedBigInteger('appointment_type_id')->nullable();

            $table->unsignedTinyInteger('sort_no')->nullable();
            $table->unsignedTinyInteger('active')->default(1);
            $table->unsignedBigInteger('account_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('account_id', 'appointment_statuses_account')
                ->references('id')
                ->on('accounts');
            $table->foreign('appointment_type_id',
                'appointment_statuses_appointment_type')
                ->references('id')
                ->on('appointment_statuses');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('appointment_statuses');
    }
}
