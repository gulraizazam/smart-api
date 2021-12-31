<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSmsLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sms_logs', function (Blueprint $table) {
            $table->id();
            $table->text('to');
            $table->string('log_type', 100)->default('sms');
            $table->text('text');
            $table->text('mask')->nullable();
            $table->unsignedTinyInteger('status')->nullable();
            $table->text('sms_data')->nullable();
            $table->text('error_msg')->nullable();

            // Foreign Key Relationships
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('appointment_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();

            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->unsignedBigInteger('package_id')->nullable();
            $table->string('is_refund')->nullable();

            $table->foreign('invoice_id')
                ->references('id')
                ->on('invoices');
            $table->foreign('package_id')
                ->references('id')
                ->on('packages');

            $table->timestamps();
            $table->softDeletes();

            // Manage Foreign Key Relationships Mapping
            $table->foreign('lead_id')
                ->references('id')
                ->on('leads');
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
        Schema::dropIfExists('sms_logs');
    }
}
