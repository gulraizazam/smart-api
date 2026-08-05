<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Track the two Call Control legs the bridge-based click-to-call flow
 * originates: one PSTN leg to the customer, one SIP leg to the agent's
 * WebRTC-registered browser. Once BOTH answer, TelnyxController bridges
 * them; `bridged_at` records when.
 *
 * Why not reuse `telnyx_call_control_id`? That column existed for a
 * single-leg outbound (browser-originates-directly). The new architecture
 * has two legs per call — different lifecycle, different failure modes.
 * We keep the old column dormant (columns cost nothing) so a rollback is
 * an env flip, not a data restore.
 *
 * Additive-only, guarded, safe on re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_calls', function (Blueprint $table): void {
            if (! Schema::hasColumn('lead_calls', 'telnyx_customer_ccid')) {
                $table->string('telnyx_customer_ccid', 128)->nullable()
                    ->comment('Call Control ID of the PSTN leg (customer). Bridge target A.');
                $table->index('telnyx_customer_ccid', 'idx_lc_customer_ccid');
            }
            if (! Schema::hasColumn('lead_calls', 'telnyx_agent_ccid')) {
                $table->string('telnyx_agent_ccid', 128)->nullable()
                    ->comment('Call Control ID of the SIP leg to the agent WebRTC endpoint. Bridge target B.');
                $table->index('telnyx_agent_ccid', 'idx_lc_agent_ccid');
            }
            if (! Schema::hasColumn('lead_calls', 'customer_answered_at')) {
                $table->timestamp('customer_answered_at')->nullable()
                    ->comment('When the customer PSTN leg answered.');
            }
            if (! Schema::hasColumn('lead_calls', 'agent_answered_at')) {
                $table->timestamp('agent_answered_at')->nullable()
                    ->comment('When the agent WebRTC leg answered.');
            }
            if (! Schema::hasColumn('lead_calls', 'bridged_at')) {
                $table->timestamp('bridged_at')->nullable()
                    ->comment('When the two legs were bridged (both had answered).');
            }
        });
    }

    public function down(): void
    {
        Schema::table('lead_calls', function (Blueprint $table): void {
            if (Schema::hasColumn('lead_calls', 'telnyx_customer_ccid')) {
                $table->dropIndex('idx_lc_customer_ccid');
                $table->dropColumn('telnyx_customer_ccid');
            }
            if (Schema::hasColumn('lead_calls', 'telnyx_agent_ccid')) {
                $table->dropIndex('idx_lc_agent_ccid');
                $table->dropColumn('telnyx_agent_ccid');
            }
            foreach (['customer_answered_at', 'agent_answered_at', 'bridged_at'] as $col) {
                if (Schema::hasColumn('lead_calls', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
