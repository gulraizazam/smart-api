<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAppointmentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->string('name', 500)->nullable();
            $table->string('random_id', 50)->nullable();

            $table->date('scheduled_date')->nullable();
            $table->time('scheduled_time')->nullable();
            $table->text('reason')->nullable();
            $table->tinyInteger('send_message')->default(0);

            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('doctor_id');
            $table->unsignedBigInteger('region_id');
            $table->unsignedBigInteger('city_id');
            $table->unsignedBigInteger('location_id');
            $table->unsignedBigInteger('appointment_id')->nullable();
            $table->unsignedBigInteger('base_appointment_status_id')->nullable();
            $table->unsignedBigInteger('appointment_status_id')->nullable();
            $table->unsignedBigInteger('appointment_status_allow_message')->default(0);
            $table->unsignedBigInteger('cancellation_reason_id')->nullable();
            $table->unsignedBigInteger('service_id')->nullable();
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('converted_by')->nullable();

            $table->unsignedTinyInteger('active')->default(1);
            $table->unsignedTinyInteger('msg_count')->default(0);
            $table->unsignedBigInteger('counter')->nullable()->default(0);
            $table->string('consultancy_type')->nullable();
            $table->string('coming_from')->nullable();
            $table->unsignedBigInteger('resource_has_rota_day_id')->nullable();
            $table->unsignedBigInteger('resource_has_rota_day_id_for_machine')->nullable();
            $table->unsignedBigInteger('appointment_type_id')->nullable();
            $table->integer('scheduled_at_count')->default(0);
            $table->date('first_scheduled_date')->nullable();
            $table->time('first_scheduled_time')->nullable();
            $table->integer('first_scheduled_count')->default(0);
            $table->unsignedBigInteger('account_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Manage Foreign Key Relationships
            $table->foreign('account_id', 'appointments_account')
                ->references('id')
                ->on('accounts');
            $table->foreign('appointment_type_id',
                'appointments_appointment_type')
                ->references('id')
                ->on('appointment_types');
            $table->foreign('lead_id')
                ->references('id')
                ->on('leads');
            $table->foreign('patient_id')
                ->references('id')
                ->on('users');
            $table->foreign('doctor_id')
                ->references('id')
                ->on('users');
            $table->foreign('region_id')
                ->references('id')
                ->on('regions');
            $table->foreign('city_id')
                ->references('id')
                ->on('cities');
            $table->foreign('location_id')
                ->references('id')
                ->on('locations');
            $table->foreign('service_id')
                ->references('id')
                ->on('services');
            $table->foreign('resource_id',
                'appointments_resource_id')
                ->references('id')
                ->on('resources');
            $table->foreign('appointment_status_id')
                ->references('id')
                ->on('appointment_statuses');
            $table->foreign('cancellation_reason_id')
                ->references('id')
                ->on('cancellation_reasons');
            $table->foreign('created_by')
                ->references('id')
                ->on('users');
            $table->foreign('updated_by')
                ->references('id')
                ->on('users');
            $table->foreign('converted_by')
                ->references('id')
                ->on('users');
            $table->foreign('resource_has_rota_day_id',
                'appointments_resource_has_rota_day')
                ->references('id')
                ->on('resource_has_rota_days');
            $table->foreign('resource_has_rota_day_id_for_machine',
                'appointments_resource_has_rota_day_machine')
                ->references('id')
                ->on('resource_has_rota_days');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('appointments');
    }
}
