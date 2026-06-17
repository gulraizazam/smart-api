<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * One-time cleanup of never-used Sanctum PATs left by the old login flow.
     *
     * Before AuthController::login was gated on `device_name` (2026-06-17), it
     * minted a PAT named 'login' on EVERY login, including the cookie-mode SPA
     * — which authenticates via the web session and discards the token. On prod
     * these had piled up to 576 tokens across 71 users, ALL with
     * last_used_at = NULL: provably dead credentials no client ever
     * authenticated with. They are a latent credential exposure, so we delete
     * them.
     *
     * Scoped precisely to name='login' AND last_used_at IS NULL — a token a real
     * bearer client minted-and-used (last_used_at set) or any future
     * device-named token is never touched.
     */
    public function up(): void
    {
        DB::table('personal_access_tokens')
            ->where('name', 'login')
            ->whereNull('last_used_at')
            ->delete();
    }

    /**
     * Irreversible by design: the deleted rows were never-used dead
     * credentials — there is nothing meaningful (or safe) to recreate.
     */
    public function down(): void
    {
        // No-op.
    }
};
