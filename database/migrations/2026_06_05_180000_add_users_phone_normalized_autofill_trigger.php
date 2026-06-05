<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Auto-fill `users.phone_normalized` for EVERY write — including writes from
 * the legacy crm2 app, which shares this database but has no knowledge of the
 * column (it was added by crm3 in 2026_04_29_220000_add_users_search_indexes).
 *
 * Background: crm3 keeps phone_normalized in lockstep with phone via the
 * Eloquent `saving` hook on User/Patients. But crm2 inserts patients straight
 * into `users` without that hook, so every crm2-created patient lands with
 * phone_normalized = NULL and is invisible to crm3's indexed phone search.
 * The 2026_04_29 migration back-filled once, but the blanks keep accumulating
 * as crm2 staff book daily (~734 and climbing as of 2026-06-05).
 *
 * Fix: a BEFORE INSERT/UPDATE trigger that derives phone_normalized from phone
 * at the DATABASE level, so it fires no matter which app writes the row. The
 * rule is byte-for-byte the crm3 hook + the 2026_04_29 back-fill (digits only,
 * empty -> NULL), so nothing about the stored format changes. Then re-run the
 * back-fill for the rows that have gone blank since 2026_04_29.
 *
 * COEXISTENCE (additive + invisible to crm2): the trigger only ever SETS the
 * crm2-ignored `phone_normalized` column — it never rejects a write and never
 * touches any other column, so it cannot break a crm2 insert/update. crm2 has
 * zero references to phone_normalized (verified across its whole tree). The
 * `phone` column crm2 reads/displays is left exactly as entered.
 *
 * Single-statement (`SET NEW.x = ...`) bodies — no BEGIN/END, no inner `;` —
 * so they load cleanly. Mirrored into the test DB (hydrated from a schema dump
 * that carries no triggers) by
 * Tests\Concerns\RefreshTestDatabase::installUsersPhoneNormalizedTrigger.
 */
return new class extends Migration
{
    /** Digits-only of NEW.phone, empty -> NULL. Matches the crm3 saving hook. */
    private const TRIGGER_EXPR = "NULLIF(REGEXP_REPLACE(COALESCE(NEW.phone, ''), '[^0-9]', ''), '')";

    public function up(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS users_phone_normalized_bi');
        DB::unprepared(
            'CREATE TRIGGER users_phone_normalized_bi BEFORE INSERT ON users '
            .'FOR EACH ROW SET NEW.phone_normalized = '.self::TRIGGER_EXPR
        );

        DB::unprepared('DROP TRIGGER IF EXISTS users_phone_normalized_bu');
        DB::unprepared(
            'CREATE TRIGGER users_phone_normalized_bu BEFORE UPDATE ON users '
            .'FOR EACH ROW SET NEW.phone_normalized = '.self::TRIGGER_EXPR
        );

        // Back-fill the rows that have gone blank since the 2026_04_29 one-off.
        // Batched + guarded so it can't loop forever: a row whose phone has no
        // digits cleans to '' -> NULL and is excluded by the WHERE, so it's
        // never re-selected. Tiny today (~700 rows) but batched keeps the
        // shared `users` table lock-free while crm2 is live.
        do {
            $affected = DB::update(
                "UPDATE users
                 SET phone_normalized = NULLIF(REGEXP_REPLACE(COALESCE(phone, ''), '[^0-9]', ''), '')
                 WHERE phone_normalized IS NULL
                   AND REGEXP_REPLACE(COALESCE(phone, ''), '[^0-9]', '') <> ''
                 LIMIT 2000"
            );
        } while ($affected > 0);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS users_phone_normalized_bi');
        DB::unprepared('DROP TRIGGER IF EXISTS users_phone_normalized_bu');
        // The back-fill only populated a crm2-ignored column from existing
        // row data — there is nothing meaningful to reverse.
    }
};
