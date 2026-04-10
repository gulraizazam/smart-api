<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDoctorGoogleReviewsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('doctor_google_reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id')->index();
            $table->unsignedBigInteger('doctor_id')->index();
            $table->unsignedTinyInteger('month')->index();
            $table->year('year')->index();
            $table->unsignedInteger('review_count')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['account_id', 'doctor_id', 'month', 'year'], 'doctor_reviews_unique');

            $table->foreign('account_id', 'doctor_reviews_account')
                ->references('id')->on('accounts');
            $table->foreign('doctor_id', 'doctor_reviews_doctor')
                ->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('doctor_google_reviews');
    }
}
