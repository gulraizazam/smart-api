<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Round 4 Auth-A1 — per-attempt login audit log.
 *
 * One row per authentication attempt against the web or API login
 * endpoints. Captures enough to forensically answer "who tried to sign
 * in as X, from where, and what happened?" after an incident.
 *
 * `user_id` is nullable because we record rows for email addresses that
 * don't resolve to any account (enumeration probes). `outcome` is a
 * string tag — cheaper than an enum and lets us add new states without
 * a schema migration.
 *
 * Indexed for the two forensic queries we expect: "history for a given
 * email" (brute-force target) and "activity from a given IP" (attacker).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_attempts', function (Blueprint $table) {
            $table->id();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->string('email', 190)->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('ip', 45)->nullable()->index();
            $table->string('user_agent', 512)->nullable();
            $table->string('channel', 16); // 'web' | 'api'
            $table->string('outcome', 32)->index();
            $table->json('meta')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_attempts');
    }
};
