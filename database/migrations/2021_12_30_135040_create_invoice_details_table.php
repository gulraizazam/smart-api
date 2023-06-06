<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInvoiceDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('invoice_details', function (Blueprint $table) {
            $table->id();
            $table->tinyInteger('qty');
            $table->string('discount_name')->nullable();
            $table->string('discount_type')->nullable();
            $table->double('discount_price', 11, 2)->nullable();
            $table->double('service_price', 11, 2);
            $table->double('net_amount', 11, 2);

            $table->unsignedBigInteger('discount_id')->nullable();
            $table->unsignedBigInteger('service_id');
            $table->unsignedBigInteger('package_id')->nullable();
            $table->unsignedBigInteger('invoice_id');
            $table->unsignedBigInteger('package_service_id')->nullable();

            $table->unsignedTinyInteger('active')->default(1);

            $table->double('tax_exclusive_serviceprice', 11, 2)->default(0.00);
            $table->double('tax_percenatage', 11, 2)->default(0.00);
            $table->double('tax_price', 11, 2)->default(0.00);
            $table->double('tax_including_price', 11, 2)->default(0.00);
            $table->unsignedTinyInteger('is_exclusive')->nullable();

            $table->timestamps();
            $table->softDeletes();

            /*Manage foreign Keys relationship*/
            $table->foreign('package_service_id', 'package_service_invoice_id')->references('id')
                ->on('package_services');
            $table->foreign('discount_id')->references('id')->on('discounts');
            $table->foreign('service_id')->references('id')->on('services');
            $table->foreign('package_id')->references('id')->on('packages');
            $table->foreign('invoice_id')->references('id')->on('invoices');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('invoice_details');
    }
}
