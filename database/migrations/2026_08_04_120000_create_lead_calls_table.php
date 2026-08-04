<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per outbound or inbound call attempt on a lead.
 *
 * Filled progressively by the click-to-call flow (LeadCallController@initiate
 * inserts the row on the SPA-side "Call" click; Plivo webhooks update it as
 * the call rings, answers, and hangs up). The recording that Plivo produces
 * once the call ends is stored separately in `lead_recordings` (1:1).
 *
 * `user_id` (the agent) is nullable because inbound calls exist as rows the
 * moment the customer's leg rings us — Laravel picks the target agent from
 * the last outbound row on this lead, then patches user_id in.
 *
 * `plivo_call_uuid` is what Plivo sends back on every webhook, so we index it
 * for O(1) row lookup on status/recording callbacks. It's nullable + unique
 * (uniqueness applies only when set — MySQL allows multiple NULLs).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_calls', function (Blueprint $table) {
            $table->id();

            // Account, users, AND leads are unsignedInteger in this legacy
            // DB despite `leads` using $table->id() in its migration — the
            // real column is `int unsigned` not `bigint`. FK types must match
            // exactly. (See the expense_attachments migration for the same
            // "legacy int, not bigint" note on account_id/expense_id.)
            $table->unsignedInteger('account_id');
            $table->unsignedInteger('lead_id');
            $table->unsignedInteger('user_id')->nullable()
                ->comment('Agent who placed / took the call. Nullable for inbound rows before we route them to an agent.');

            $table->enum('direction', ['outbound', 'inbound']);

            $table->string('plivo_call_uuid', 64)->nullable()
                ->comment('Plivo CallUUID; the join key on every webhook.');
            $table->string('from_number', 24)->comment('E.164, e.g. +9221XXXXXXX.');
            $table->string('to_number', 24)->comment('E.164, e.g. +923001234567.');

            $table->enum('status', [
                'initiated',
                'ringing',
                'in_progress',
                'completed',
                'failed',
                'busy',
                'no_answer',
                'canceled',
            ])->default('initiated');

            $table->timestamp('initiated_at')->useCurrent();
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable()
                ->comment('Full call duration (billable seconds). Populated on hangup callback.');
            $table->string('hangup_cause', 64)->nullable()
                ->comment('Plivo HangupCause, e.g. NORMAL_CLEARING / USER_BUSY / NO_ANSWER.');

            // Agent-picked disposition after the call ends. Kept as enum
            // + free-text so basic filtering works without free-text search.
            $table->enum('outcome', [
                'interested',
                'not_interested',
                'callback',
                'wrong_number',
                'no_answer',
                'other',
            ])->nullable();
            $table->text('outcome_notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('account_id')->references('id')->on('accounts');
            $table->foreign('lead_id')->references('id')->on('leads');
            $table->foreign('user_id')->references('id')->on('users');

            // Index for "this lead's call history" (drawer Call History tab).
            $table->index(['account_id', 'lead_id', 'initiated_at'], 'lead_calls_lead_time_idx');
            // Index for "my calls today" reports keyed by agent.
            $table->index(['account_id', 'user_id', 'initiated_at'], 'lead_calls_user_time_idx');
            // Fast lookup on every webhook — Plivo posts the CallUUID as its join key.
            $table->index('plivo_call_uuid', 'lead_calls_plivo_uuid_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_calls');
    }
};
