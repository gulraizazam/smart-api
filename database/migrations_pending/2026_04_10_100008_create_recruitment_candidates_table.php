<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recruitment_candidates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->unsignedBigInteger('designation_id');
            $table->unsignedInteger('city_id');
            $table->unsignedInteger('location_id');
            $table->string('status')->default('scheduled'); // scheduled, in_review, shortlisted, on_hold, offer, hired, offer_declined, rejected, blacklisted
            $table->string('cv_file_path')->nullable();
            $table->string('cv_google_drive_url')->nullable();
            $table->string('cv_google_drive_file_id')->nullable();
            $table->string('cv_drive_upload_status')->default('pending');
            $table->text('notes')->nullable();
            $table->unsignedInteger('converted_user_id')->nullable();

            $table->unsignedInteger('account_id');
            $table->unsignedInteger('created_by');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'account_id']);
            $table->index('account_id');
            $table->index('designation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_candidates');
    }
};
