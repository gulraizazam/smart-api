<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * H2 — Add failed-login lockout columns and TOTP 2FA columns to `users`.
 *
 * - failed_login_attempts: counter, reset on successful login.
 * - locked_until: timestamp; when set in the future, login is rejected.
 * - totp_secret: encrypted via EncryptedLegacy cast on the model.
 * - totp_enabled_at: when not null, the user has confirmed TOTP enrollment
 *   and must pass the 2FA challenge on every session.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'failed_login_attempts')) {
                $table->unsignedTinyInteger('failed_login_attempts')->default(0);
            }
            if (! Schema::hasColumn('users', 'locked_until')) {
                $table->timestamp('locked_until')->nullable();
            }
            if (! Schema::hasColumn('users', 'totp_secret')) {
                // TEXT — holds Laravel encryption envelope (~280 chars).
                $table->text('totp_secret')->nullable();
            }
            if (! Schema::hasColumn('users', 'totp_enabled_at')) {
                $table->timestamp('totp_enabled_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        // No-op: dropping security columns is not safe to automate.
    }
};
