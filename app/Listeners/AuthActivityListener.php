<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\ActivityLogTier;
use App\Models\Activity;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Writes a security-tier row to `activities` for every auth event.
 *
 * Covers: login, logout, failed login, lockout, password reset.
 *
 * Privacy posture:
 *   - Password is NEVER touched (Laravel's Failed event carries it in the
 *     credentials array; we read only the identifier key).
 *   - The email/identifier on Failed and Lockout is hashed (SHA-256, first
 *     16 hex chars). That's enough to correlate repeated attempts against
 *     the same account without persisting the address itself.
 *   - Successful Login/Logout record the user_id directly since we already
 *     have an authenticated session.
 *
 * Writes synchronously (no queue dispatch). Reasons:
 *   - The insert is a single row under 2KB; p99 is sub-ms.
 *   - Queue driver here is the database (no Redis per infra constraint) and
 *     we must not make the audit trail dependent on queue liveness.
 *   - Failed login / lockout records are exactly what we'd want if the
 *     queue itself is under attack.
 *
 * Registered in App\Providers\AppServiceProvider::registerAuthEventListeners().
 */
class AuthActivityListener
{
    public function onLogin(Login $event): void
    {
        $this->write(
            action: 'login',
            activityType: 'login',
            userId: $this->userIdOf($event->user),
            accountId: $this->accountIdFrom($event->user),
            description: sprintf(
                'Login via %s guard from %s',
                $event->guard,
                $this->requestSignature(),
            ),
        );
    }

    public function onLogout(Logout $event): void
    {
        $this->write(
            action: 'logout',
            activityType: 'logout',
            userId: $this->userIdOf($event->user),
            accountId: $this->accountIdFrom($event->user),
            description: sprintf(
                'Logout via %s guard from %s',
                $event->guard,
                $this->requestSignature(),
            ),
        );
    }

    public function onFailed(Failed $event): void
    {
        $this->write(
            action: 'login_failed',
            activityType: 'login_failed',
            userId: $this->userIdOf($event->user),
            accountId: $this->accountIdFrom($event->user),
            description: sprintf(
                'Failed login via %s guard from %s (identifier_hash=%s)',
                $event->guard,
                $this->requestSignature(),
                $this->hashIdentifier($this->extractIdentifier($event->credentials)),
            ),
        );
    }

    public function onLockout(Lockout $event): void
    {
        $request = $event->request;
        $identifier = (string) ($request->input('email') ?? $request->input('username') ?? '');

        $this->write(
            action: 'account_locked',
            activityType: 'account_locked',
            userId: null,
            accountId: null,
            description: sprintf(
                'Lockout from %s (identifier_hash=%s)',
                $this->requestSignature($request),
                $this->hashIdentifier($identifier),
            ),
        );
    }

    public function onPasswordReset(PasswordReset $event): void
    {
        $this->write(
            action: 'password_reset',
            activityType: 'password_reset',
            userId: $this->userIdOf($event->user),
            accountId: $this->accountIdFrom($event->user),
            description: sprintf('Password reset completed from %s', $this->requestSignature()),
        );
    }

    private function userIdOf(mixed $user): ?int
    {
        $id = $user?->getAuthIdentifier();

        return is_numeric($id) ? (int) $id : null;
    }

    private function write(
        string $action,
        string $activityType,
        ?int $userId,
        ?int $accountId,
        string $description,
    ): void {
        try {
            Activity::create([
                'account_id' => $accountId,
                'action' => $action,
                'activity_type' => $activityType,
                'log_tier' => ActivityLogTier::Security->value,
                'description' => mb_substr($description, 0, 2000),
                'user_id' => $userId,
                'created_by' => $userId,
            ]);
        } catch (\Throwable $e) {
            // Never let audit-log failure break auth. Log the failure itself
            // and continue — a missing audit row is preferable to a user
            // being unable to log in / out.
            Log::error('activities.auth_listener.write_failed', [
                'event' => 'activities.auth_listener.write_failed',
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function requestSignature(?Request $request = null): string
    {
        $request ??= request();

        if (! $request instanceof Request) {
            return 'ip=unknown';
        }

        $ip = $request->ip() ?? 'unknown';
        $ua = (string) ($request->userAgent() ?? '');
        $uaFingerprint = $ua === '' ? 'unknown' : substr(hash('sha256', $ua), 0, 12);

        return sprintf('ip=%s ua=%s', $ip, $uaFingerprint);
    }

    private function extractIdentifier(array $credentials): string
    {
        foreach (['email', 'username', 'login'] as $key) {
            if (isset($credentials[$key]) && is_string($credentials[$key])) {
                return $credentials[$key];
            }
        }

        return '';
    }

    private function hashIdentifier(string $identifier): string
    {
        $identifier = strtolower(trim($identifier));

        if ($identifier === '') {
            return 'none';
        }

        return substr(hash('sha256', $identifier), 0, 16);
    }

    private function accountIdFrom(mixed $user): ?int
    {
        $value = $user?->account_id;

        return is_numeric($value) ? (int) $value : null;
    }
}
