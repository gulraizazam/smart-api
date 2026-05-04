<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-appointment idempotency flags for the Meta CAPI `booked` and
 * `arrived` events — same shape as the pre-existing
 * `meta_purchase_sent` column added by the 2024_12_31 migration.
 *
 * Why per-appointment (not per-lead like Converted): each appointment
 * IS its own funnel entry for ad attribution. Reschedule of the same
 * row should NOT re-fire (same flag persists); a new appointment for
 * the same lead later SHOULD fire (different row, fresh flag).
 *
 * Without these flags, every reschedule + every duplicate booking
 * attempt re-sent the event to Meta, inflating Booked/Arrived counts
 * and biasing Lookalike Audiences + bid optimisation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->boolean('meta_booked_sent')->default(false)->after('meta_purchase_sent');
            $table->boolean('meta_arrived_sent')->default(false)->after('meta_booked_sent');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['meta_booked_sent', 'meta_arrived_sent']);
        });
    }
};
