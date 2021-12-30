<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePabaoRecordsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pabao_records', function (Blueprint $table) {
            $table->id();
            $table->text('client')->nullable();
            $table->string('invoice_no')->nullable();
            $table->date('issue_date')->nullable();
            $table->string('employee')->nullable();
            $table->double('total_amount', 11, 2)->default(0.00);
            $table->double('paid_amount', 11, 2)->default(0.00);
            $table->double('outstanding_amount', 11, 2)->default(0.00);
            $table->double('total_spend', 11, 2)->default(0.00);
            $table->unsignedBigInteger('total_visits')->nullable();
            $table->unsignedBigInteger('last_visit_days_ago')->nullable();
            $table->string('new_client')->nullable();
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->date('last_modified')->nullable();
            $table->unsignedTinyInteger('active')->nullable();
            $table->string('country')->nullable();
            $table->string('salutation')->nullable();
            $table->text('address_1')->nullable();
            $table->text('address_2')->nullable();
            $table->string('post_code')->nullable();
            $table->string('mobile')->nullable();
            $table->string('phone')->nullable();
            $table->string('town')->nullable();
            $table->text('full_address')->nullable();
            $table->string('gender')->nullable();
            $table->string('email')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('privacy_policy')->nullable();
            $table->string('marketing_optin_email')->nullable();
            $table->string('marketing_optin_sms')->nullable();
            $table->string('marketing_optin_newsletter')->nullable();
            $table->string('marketing_source')->nullable();
            $table->unsignedBigInteger('age')->nullable();
            $table->string('insurer_name')->nullable();
            $table->string('contract_client')->nullable();
            $table->unsignedBigInteger('appointments_attended_total')->nullable();
            $table->unsignedBigInteger('appointments_attended')->nullable();
            $table->text('online_bookings')->nullable();
            $table->unsignedBigInteger('appointments_dna')->nullable();
            $table->unsignedBigInteger('appointments_rescheduled')->nullable();
            $table->date('appointments_date_first')->nullable();
            $table->date('appointments_date_last')->nullable();
            $table->double('outstanding_balance', 11, 2)->default(0.00);
            $table->double('amount_balance', 11, 2)->default(0.00);
            $table->string('first_booking_with')->nullable();
            $table->text('first_booking_service')->nullable();
            $table->string('membership_number')->nullable();
            $table->string('future_booking')->nullable();
            $table->date('future_booking_date')->nullable();
            $table->string('next_appointment')->nullable();
            $table->string('client_created_by')->nullable();
            $table->unsignedBigInteger('episode_id')->nullable();
            $table->unsignedBigInteger('client_sys_id')->nullable();
            $table->string('location')->nullable();

            $table->unsignedBigInteger('location_id');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('account_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('location_id')->references('id')->on('locations');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('account_id')->references('id')->on('accounts');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('pabao_records');
    }
}
