<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Live + historical alert state for the Management Dashboard.
 *
 * AlertEngine upserts one row per (alert_key × scope) combination. first_seen_at
 * is preserved across runs; last_seen_at is bumped on every breach. When the
 * underlying metric recovers the engine sets resolved_at and the row drops out
 * of the active feed (but stays in the table as history).
 *
 * Ack marks the alert as seen by a user (still shown). Snooze suppresses it
 * from the feed until snoozed_until (or until recovery, whichever first).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_alert_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->string('alert_key', 64);
            $table->string('scope', 128);
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('acknowledged_at')->nullable();
            $table->unsignedBigInteger('acknowledged_by')->nullable();
            $table->timestamp('snoozed_until')->nullable();
            $table->unsignedBigInteger('snoozed_by')->nullable();
            $table->string('snooze_reason', 255)->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->json('context')->nullable();
            $table->timestamps();

            $table->unique(['account_id', 'alert_key', 'scope'], 'dae_account_alert_scope_unique');
            $table->index(['account_id', 'resolved_at', 'snoozed_until'], 'dae_active_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_alert_events');
    }
};
