<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePackageAdvancesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('package_advances', function (Blueprint $table) {
            $table->id();
            $table->enum('cash_flow', ['in', 'out'])->default('in');
            $table->double('cash_amount', 11, 2)->default(0.00);
            $table->unsignedTinyInteger('active')->default(1);
            $table->unsignedBigInteger('is_refund')->default(0);
            $table->unsignedBigInteger('is_adjustment')->default(0);
            $table->longText('refund_note')->nullable();
            $table->unsignedBigInteger('is_cancel')->default(0);
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->unsignedBigInteger('payment_mode_id')->nullable();
            $table->unsignedBigInteger('account_id')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('package_id')->nullable();
            $table->unsignedBigInteger('invoice_id')->nullable();

            $table->unsignedBigInteger('appointment_type_id')->nullable();
            $table->unsignedBigInteger('appointment_id')->nullable();
            $table->unsignedTinyInteger('is_tax')->default(0);
            $table->unsignedBigInteger('location_id')->nullable();

            // Manage Foreign Key Relationships
            $table->foreign('location_id', 'package_location_id')->references('id')->on('locations');
            $table->foreign('invoice_id', 'package_advances_invoice_id')
                ->references('id')
                ->on('invoices');
            $table->foreign('appointment_type_id',
                'patient_balances_appointment_type')
                ->references('id')
                ->on('appointment_types');
            $table->foreign('appointment_id',
                'patient_balances_appointment')
                ->references('id')
                ->on('appointments');
            $table->foreign('patient_id')->references('id')->on('users');
            $table->foreign('payment_mode_id')->references('id')->on('payment_modes');
            $table->foreign('account_id')->references('id')->on('accounts');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('package_id')->references('id')->on('packages');

            $table->timestamps();
            $table->softDeletes();

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('package_advances');
    }
}
