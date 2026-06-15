<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Add four more team-requested WhatsApp tags (Decide later / Booked /
 * Complaint / Job). Idempotent (insertOrIgnore on the unique account_id+name)
 * so it's safe even if a name already exists — "Booked" re-adds the default
 * that had been deleted. Data backfill on the crm3-only whatsapp_tags table;
 * the team can edit/delete these freely. No-op down (rows may already be
 * applied to chats or edited).
 */
return new class extends Migration
{
    public function up(): void
    {
        $accountId = (int) config('whatsapp.account_id', 1);
        $now = now();

        $tags = [
            ['name' => 'Decide later', 'color' => 'warning', 'is_muting' => false],
            ['name' => 'Booked', 'color' => 'success', 'is_muting' => false],
            ['name' => 'Complaint', 'color' => 'danger', 'is_muting' => false],
            ['name' => 'Job', 'color' => 'brand', 'is_muting' => false],
        ];

        foreach ($tags as $tag) {
            DB::table('whatsapp_tags')->insertOrIgnore([
                'account_id' => $accountId,
                'name' => $tag['name'],
                'color' => $tag['color'],
                'is_muting' => $tag['is_muting'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // No-op: tags may already be applied to chats (pivot rows) or edited.
    }
};
