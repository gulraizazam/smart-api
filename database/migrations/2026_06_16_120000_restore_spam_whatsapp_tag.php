<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Restore the "Spam" tag, which was accidentally hard-deleted from the inbox
 * (tag delete is now removed entirely — see the routes/UI change in this batch).
 * Idempotent via the (account_id, name) unique key; only the tag DEFINITION is
 * restored — which chats were tagged Spam was lost on detach and can't be
 * recovered. Re-seeds ONLY Spam (not the full default set, which would resurrect
 * the intentionally-retired Follow-up / Hot lead tags).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('whatsapp_tags')->insertOrIgnore([
            'account_id' => 1,
            'name' => 'Spam',
            'color' => 'danger',
            'is_muting' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Data re-seed — no destructive rollback (matches the other tag seeds).
    }
};
