<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStaffTargetsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('staff_targets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id')->index();
            $table->unsignedBigInteger('staff_id')->index();
            $table->unsignedBigInteger('location_id')->default(1)->index();
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->unsignedTinyInteger('total_services')->default(0);
            $table->unsignedTinyInteger('month')->index();
            $table->year('year')->index();
            $table->timestamps();
            $table->softDeletes();

            // Manage Foreign Key Relationships
            $table->foreign('account_id', 'staff_targets_account')
                ->references('id')->on('accounts');
            $table->foreign('staff_id', 'staff_targets_staff')
                ->references('id')->on('users');
            $table->foreign('location_id', 'staff_targets_location')
                ->references('id')->on('locations');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('staff_targets');
    }
}
