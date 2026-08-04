<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add Telnyx-side identifiers to the call + recording tables.
 *
 * We keep the pre-existing `plivo_call_uuid` / `plivo_recording_id` columns
 * in place (dormant) so a rollback stays trivial. The new provider-tagged
 * columns coexist alongside them; a future cleanup migration drops the
 * plivo_* columns once Telnyx is proven in prod.
 *
 * Additive-only, guarded with hasColumn() so re-runs are safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_calls', function (Blueprint $table): void {
            if (! Schema::hasColumn('lead_calls', 'telnyx_call_control_id')) {
                $table->string('telnyx_call_control_id', 128)->nullable()
                    ->comment('Telnyx Call Control ID (encrypted string). Join key on every webhook.');
                $table->index('telnyx_call_control_id', 'idx_lead_calls_telnyx_ccid');
            }
            if (! Schema::hasColumn('lead_calls', 'telnyx_call_leg_id')) {
                $table->string('telnyx_call_leg_id', 128)->nullable()
                    ->comment('Telnyx Call Leg ID (UUID). One per leg; call_control_id spans a bridged pair.');
            }
            if (! Schema::hasColumn('lead_calls', 'provider')) {
                // 'telnyx' for anything created after this migration;
                // 'plivo' as legacy — but no plivo rows ever hit prod
                // (Plivo signup was blocked from PK before deploy), so the
                // legacy label is documentation only.
                $table->string('provider', 16)->default('telnyx')
                    ->comment('Voice provider that created this row: telnyx (current) or plivo (legacy).');
            }
        });

        Schema::table('lead_recordings', function (Blueprint $table): void {
            if (! Schema::hasColumn('lead_recordings', 'telnyx_recording_id')) {
                $table->string('telnyx_recording_id', 128)->nullable()
                    ->comment('Telnyx recording ID from call.recording.saved payload. Dedup key.');
                $table->index('telnyx_recording_id', 'idx_lead_recordings_telnyx_rec_id');
            }
            if (! Schema::hasColumn('lead_recordings', 'provider')) {
                $table->string('provider', 16)->default('telnyx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('lead_calls', function (Blueprint $table): void {
            if (Schema::hasColumn('lead_calls', 'telnyx_call_control_id')) {
                $table->dropIndex('idx_lead_calls_telnyx_ccid');
                $table->dropColumn('telnyx_call_control_id');
            }
            if (Schema::hasColumn('lead_calls', 'telnyx_call_leg_id')) {
                $table->dropColumn('telnyx_call_leg_id');
            }
            if (Schema::hasColumn('lead_calls', 'provider')) {
                $table->dropColumn('provider');
            }
        });

        Schema::table('lead_recordings', function (Blueprint $table): void {
            if (Schema::hasColumn('lead_recordings', 'telnyx_recording_id')) {
                $table->dropIndex('idx_lead_recordings_telnyx_rec_id');
                $table->dropColumn('telnyx_recording_id');
            }
            if (Schema::hasColumn('lead_recordings', 'provider')) {
                $table->dropColumn('provider');
            }
        });
    }
};
