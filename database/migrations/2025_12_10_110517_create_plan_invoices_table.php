<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePlanInvoicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('plan_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->double('total_price', 11, 2);
            $table->unsignedInteger('account_id');
            $table->unsignedInteger('patient_id')->nullable();
            $table->integer('created_by');
            $table->integer('location_id');
            $table->tinyInteger('active')->default(1);
            $table->integer('package_id')->nullable();
             $table->unsignedInteger('payment_mode_id')->nullable()->comment('FK to payment_modes table');
            $table->enum('invoice_type', ['exempt', 'taxable'])->default('exempt')->comment('exempt = 70% bank + 95% cash, taxable = 30% bank + 5% cash');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('account_id');
            $table->index('patient_id');
            $table->index('location_id');
            $table->index('invoice_type');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('plan_invoices');
    }
}
