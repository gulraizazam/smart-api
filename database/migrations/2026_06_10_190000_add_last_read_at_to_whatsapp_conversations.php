<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WhatsApp inbox (Phase 2): `last_read_at` marks when the team last opened
 * the conversation — inbound messages newer than it count as unread (NULL =
 * never opened, everything unread).
 *
 * COEXISTENCE: additive column on a crm3-only table created 2026-06-10;
 * crm2 never reads it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_conversations', function (Blueprint $table): void {
            $table->timestamp('last_read_at')->nullable()->after('last_inbound_at');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_conversations', function (Blueprint $table): void {
            $table->dropColumn('last_read_at');
        });
    }
};
