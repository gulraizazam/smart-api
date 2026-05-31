<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInvoicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->double('total_price', 11, 2)->nullabale();

            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->unsignedBigInteger('appointment_id')->nullable();
            $table->unsignedBigInteger('invoice_status_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('location_id');
            $table->unsignedBigInteger('doctor_id');
            $table->unsignedTinyInteger('active')->default(1);

            $table->unsignedTinyInteger('is_exclusive')->nullable();

            $table->timestamps();
            $table->softDeletes();

            /*Manage foreign Keys relationship*/
            $table->foreign('invoice_status_id', 'invoices_invoice_status')->references('id')->on('invoice_statuses');
            $table->foreign('account_id')->references('id')->on('accounts');
            $table->foreign('patient_id')->references('id')->on('users');
            $table->foreign('appointment_id')->references('id')->on('appointments');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('location_id')->references('id')->on('locations');
            $table->foreign('doctor_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('invoices');
    }
}
