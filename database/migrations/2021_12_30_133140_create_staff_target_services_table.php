<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStaffTargetServicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('staff_target_services', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id')->index();
            $table->unsignedBigInteger('location_id')->default(1)->index();
            $table->unsignedBigInteger('staff_target_id')->index();
            $table->unsignedBigInteger('staff_id')->index();
            $table->unsignedBigInteger('service_id')->index();
            $table->decimal('target_amount', 12, 2)->default(0);
            $table->unsignedTinyInteger('target_services')->default(0);
            $table->unsignedTinyInteger('month')->index();
            $table->year('year')->index();
            $table->timestamps();
            $table->softDeletes();

            // Manage Foreign Key Relationships
            $table->foreign('account_id', 'staff_target_services_account')
                ->references('id')->on('accounts');

            $table->foreign('location_id', 'staff_target_services_location')
                ->references('id')->on('locations');

            $table->foreign('staff_target_id', 'staff_target_services_staff_target')
                ->references('id')->on('staff_targets');

            $table->foreign('staff_id', 'staff_target_services_staff')
                ->references('id')->on('users');

            $table->foreign('service_id', 'staff_target_services_service')
                ->references('id')->on('services');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('staff_target_services');
    }
}
