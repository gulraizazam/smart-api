<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Adds `sms_last_attempt_at` to `appointments` so the booking-SMS
 * cron can:
 *   1. Retry until the appointment time has passed (replaces the
 *      old "give up after first attempt" behavior).
 *   2. Throttle retries — fast cadence (every minute) for the first
 *      30 minutes after booking, slow cadence (every 30 min)
 *      thereafter. Bad-phone-number rows would otherwise hammer the
 *      SMS provider every minute for days.
 *
 * NULL = never attempted yet. The cron writes NOW() on each
 * attempt regardless of provider success — used purely for cadence
 * spacing, not for status.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function ($table): void {
            $table->timestamp('sms_last_attempt_at')->nullable()->after('msg_count');
            $table->index('sms_last_attempt_at', 'idx_appointments_sms_last_attempt_at');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function ($table): void {
            $table->dropIndex('idx_appointments_sms_last_attempt_at');
            $table->dropColumn('sms_last_attempt_at');
        });
    }
};
