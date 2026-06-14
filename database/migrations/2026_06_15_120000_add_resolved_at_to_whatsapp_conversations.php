<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conversation triage: `resolved_at` marks a chat as handled/done (NULL = open).
 * A new inbound message auto-reopens it (cleared in the webhook). No index — the
 * table is one row per WhatsApp user (small); the resolved filter scans cheaply.
 *
 * COEXISTENCE: additive column on the crm3-only whatsapp_conversations table —
 * crm2 never reads it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_conversations', function (Blueprint $table): void {
            $table->timestamp('resolved_at')->nullable()->after('assigned_to_id');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_conversations', function (Blueprint $table): void {
            $table->dropColumn('resolved_at');
        });
    }
};
