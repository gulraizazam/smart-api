<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the WhatsApp conversation "resolve/done" triage: the team tracks status
 * with tags (e.g. "Booked") instead, so `resolved_at` is no longer read or
 * written. Guarded with hasColumn so it's safe whether or not the add-column
 * migration has reached this environment yet.
 *
 * COEXISTENCE: whatsapp_conversations is a crm3-only table — crm2 never reads
 * this column, so dropping it cannot affect crm2.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('whatsapp_conversations', 'resolved_at')) {
            return;
        }

        Schema::table('whatsapp_conversations', function (Blueprint $table): void {
            $table->dropColumn('resolved_at');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('whatsapp_conversations', 'resolved_at')) {
            return;
        }

        Schema::table('whatsapp_conversations', function (Blueprint $table): void {
            $table->timestamp('resolved_at')->nullable()->after('assigned_to_id');
        });
    }
};
