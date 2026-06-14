<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conversation assignment ("I've got this"): `assigned_to_id` points at the
 * staff member (users.id) handling a chat — NULL = unassigned. Indexed for the
 * "assigned to me" filter. Mirrors the table's existing `patient_id` column
 * (nullable user reference, index, no DB FK — users are soft-deletable).
 *
 * COEXISTENCE: additive column on the crm3-only whatsapp_conversations table —
 * crm2 never reads it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_conversations', function (Blueprint $table): void {
            $table->unsignedBigInteger('assigned_to_id')->nullable()->after('opted_out_at');
            $table->index('assigned_to_id', 'wa_conversations_assigned');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_conversations', function (Blueprint $table): void {
            $table->dropIndex('wa_conversations_assigned');
            $table->dropColumn('assigned_to_id');
        });
    }
};
