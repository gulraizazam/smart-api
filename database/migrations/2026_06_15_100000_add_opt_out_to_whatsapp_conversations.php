<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Meta-policy opt-out (compliance): when a customer texts STOP/UNSUBSCRIBE we
 * must stop messaging them. `opted_out_at` stamps when they opted out — NULL =
 * subscribed. Honored structurally by WhatsAppService before any send.
 *
 * COEXISTENCE: additive column on the crm3-only whatsapp_conversations table —
 * crm2 never reads it. No index: it's only ever read on a row already located
 * by the unique wa_id, and the table is one row per WhatsApp user (small).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_conversations', function (Blueprint $table): void {
            $table->timestamp('opted_out_at')->nullable()->after('last_read_at');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_conversations', function (Blueprint $table): void {
            $table->dropColumn('opted_out_at');
        });
    }
};
