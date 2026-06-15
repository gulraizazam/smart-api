<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Retire two default tags the team no longer wants, preserving the chats that
 * carry them:
 *   - "Follow-up" → re-tag those chats as "Decide later"
 *   - "Hot lead"  → re-tag those chats as "Interested"
 * then delete the old tag (and its pivot rows). Runs after 180000 so the
 * "Decide later" target exists. Idempotent + safe to re-run: missing tags are
 * skipped, and re-tagging uses insertOrIgnore so a chat already carrying the
 * target isn't duplicated. crm3-only whatsapp_tags / pivot — coexistence-safe.
 * No-op down (deleted tags + reassignments can't be cleanly restored).
 */
return new class extends Migration
{
    public function up(): void
    {
        $accountId = (int) config('whatsapp.account_id', 1);

        $moves = [
            ['from' => 'Follow-up', 'to' => 'Decide later'],
            ['from' => 'Hot lead', 'to' => 'Interested'],
        ];

        foreach ($moves as $move) {
            $from = DB::table('whatsapp_tags')
                ->where('account_id', $accountId)->where('name', $move['from'])->first();
            if (! $from) {
                continue;
            }

            $to = DB::table('whatsapp_tags')
                ->where('account_id', $accountId)->where('name', $move['to'])->first();

            if ($to) {
                $conversationIds = DB::table('whatsapp_conversation_tag')
                    ->where('whatsapp_tag_id', $from->id)
                    ->pluck('whatsapp_conversation_id');

                foreach ($conversationIds as $conversationId) {
                    DB::table('whatsapp_conversation_tag')->insertOrIgnore([
                        'whatsapp_conversation_id' => $conversationId,
                        'whatsapp_tag_id' => $to->id,
                    ]);
                }
            }

            DB::table('whatsapp_conversation_tag')->where('whatsapp_tag_id', $from->id)->delete();
            DB::table('whatsapp_tags')->where('id', $from->id)->delete();
        }
    }

    public function down(): void
    {
        // No-op: retired tags and their reassignments can't be cleanly restored.
    }
};
