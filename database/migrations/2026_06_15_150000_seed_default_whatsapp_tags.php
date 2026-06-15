<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed a researched default tag set (lead-lifecycle + triage, mirroring the
 * labels Respond.io / WATI / Intercom / HubSpot use for sales conversations) so
 * agents have ready-made, colour-coded options. "Spam" is muting (hide + mute).
 * Idempotent (insertOrIgnore on the unique account_id+name); the team can edit
 * or delete these freely. Data backfill, separate from the DDL migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        $accountId = (int) config('whatsapp.account_id', 1);
        $now = now();

        $tags = [
            ['name' => 'New', 'color' => 'brand', 'is_muting' => false],
            ['name' => 'Interested', 'color' => 'accent', 'is_muting' => false],
            ['name' => 'Hot lead', 'color' => 'warning', 'is_muting' => false],
            ['name' => 'Follow-up', 'color' => 'outline', 'is_muting' => false],
            ['name' => 'Booked', 'color' => 'success', 'is_muting' => false],
            ['name' => 'Not interested', 'color' => 'neutral', 'is_muting' => false],
            ['name' => 'Spam', 'color' => 'danger', 'is_muting' => true],
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
        // No-op: seeded tags may already be applied to chats (pivot rows) or
        // edited by the team — leave them in place. The table itself is removed
        // by the create-tags migration's down().
    }
};
