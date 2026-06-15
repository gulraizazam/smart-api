<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Performance indexes for the WhatsApp polling hot path. `whatsapp_messages`
 * is the one WhatsApp table that grows unbounded, and the app-wide polls hit
 * it constantly: unread-count (every 20s) finds the latest inbound, and the
 * health endpoint (every 60s) scans by direction + delivered/read/failed
 * status. Only existing index is (whatsapp_conversation_id, created_at), so
 * these direction-filtered scans were filesorting the whole table.
 *
 * Purely additive on a crm3-only table (crm2 never reads whatsapp_messages),
 * so safe under the shared-DB coexistence rule. down() drops only the two
 * indexes this migration adds (standard reversible-migration practice).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table): void {
            // unread-count's "latest inbound" + health's "latest outbound".
            $table->index(['direction', 'created_at'], 'wa_messages_direction_created');
            // lastWebhookActivityAt's "latest delivered/read/failed outbound".
            $table->index(['direction', 'status', 'updated_at'], 'wa_messages_direction_status_updated');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table): void {
            $table->dropIndex('wa_messages_direction_created');
            $table->dropIndex('wa_messages_direction_status_updated');
        });
    }
};
