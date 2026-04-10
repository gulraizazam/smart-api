<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Widen 3 columns recently bound to App\Casts\EncryptedLegacy.
     *
     * Laravel's Crypt::encryptString produces ciphertext of ~228 chars for 15-char
     * plaintext (200+ chars even for empty input due to IV+HMAC+JSON envelope).
     * The current declared widths (15, 50, 50) cannot hold encrypted output.
     *
     * STRICT_TRANS_TABLES is enabled — first re-save through the model would
     * throw "Data too long for column" and fail the user/settings update.
     *
     * varchar(500) handles plaintext up to ~150 chars with comfortable headroom.
     * No data conversion needed: existing rows are still cleartext (will be
     * encrypted on next model save). Widening alone is data-preserving.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY cnic VARCHAR(500) DEFAULT NULL");
        DB::statement("ALTER TABLE user_operator_settings MODIFY password VARCHAR(500) DEFAULT NULL");
        DB::statement("ALTER TABLE global_operator_settings MODIFY password VARCHAR(500) DEFAULT NULL");
    }

    public function down(): void
    {
        // WARNING: only safe if no encrypted ciphertext has been written yet.
        // If down() is run after rows have been encrypted, ciphertext will be
        // truncated and unrecoverable. Manual data check recommended first.
        DB::statement("ALTER TABLE users MODIFY cnic VARCHAR(15) DEFAULT NULL");
        DB::statement("ALTER TABLE user_operator_settings MODIFY password VARCHAR(50) DEFAULT NULL");
        DB::statement("ALTER TABLE global_operator_settings MODIFY password VARCHAR(50) DEFAULT NULL");
    }
};
