<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create archive table for SMS logs older than 12 months.
     *
     * Current eligible: 643K rows (~190 MB)
     * Archive command: php artisan sms:archive
     */
    public function up(): void
    {
        Schema::create('sms_logs_archive', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->string('log_type', 100)->default('sms');
            $table->text('to');
            $table->text('text');
            $table->text('mask')->nullable();
            $table->tinyInteger('status')->unsigned()->nullable();
            $table->text('sms_data')->nullable();
            $table->text('error_msg')->nullable();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('appointment_id')->nullable();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->unsignedBigInteger('package_id')->nullable();
            $table->boolean('is_refund')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('created_at', 'idx_archive_sms_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_logs_archive');
    }
};
