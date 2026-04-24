<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Round 4 Auth-A1 — central write-path for the login_attempts audit log.
 *
 * Every outcome of a login attempt (success or any failure reason) is
 * recorded here by both the web LoginController and the API AuthController,
 * so forensic queries can reconstruct authentication activity from a
 * single table regardless of which endpoint was used.
 *
 * The enumeration-safe error messaging collapses the user-visible
 * outcomes (deactivated / non-existent / wrong password all return the
 * same string) — the audit log deliberately preserves the distinction
 * so administrators can still investigate. The outcome tag is what a
 * legitimate reviewer sees, not what a probe would.
 *
 * Writes are best-effort: a DB failure here must never prevent a legit
 * user from signing in, so exceptions are caught and logged to the
 * Laravel log rather than thrown.
 */
final class LoginAuditLogger
{
    /** Successful authentication. */
    public const OUTCOME_SUCCESS = 'success';

    /** Password did not match. */
    public const OUTCOME_WRONG_PASSWORD = 'wrong_password';

    /** Email did not resolve to any user row. */
    public const OUTCOME_ACCOUNT_NOT_FOUND = 'account_not_found';

    /** User exists but `active = 0`. */
    public const OUTCOME_ACCOUNT_DEACTIVATED = 'account_deactivated';

    /** DB-backed per-account lockout is active. */
    public const OUTCOME_ACCOUNT_LOCKED = 'account_locked';

    /** IP/email throttle fired before we could check the password. */
    public const OUTCOME_RATE_LIMITED = 'rate_limited';

    /**
     * @param  array<string, mixed>|null  $meta  additional context (JSON-stored)
     */
    public function record(
        Request $request,
        string $channel,
        string $outcome,
        ?string $email = null,
        ?User $user = null,
        ?array $meta = null,
    ): void {
        try {
            DB::table('login_attempts')->insert([
                'created_at' => now(),
                'email' => $email !== null ? mb_substr($email, 0, 190) : null,
                'user_id' => $user?->id,
                'ip' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 512),
                'channel' => $channel,
                'outcome' => $outcome,
                'meta' => $meta !== null ? json_encode($meta) : null,
            ]);
        } catch (Throwable $e) {
            // Never block a login on an audit-write failure. Log and move on.
            Log::warning('login_attempts audit write failed', [
                'outcome' => $outcome,
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
