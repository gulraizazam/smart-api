<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Login flow contract:
 *
 *   1. Valid credentials → session established, account_id stashed
 *   2. Invalid credentials → user not authenticated, error flash
 *   3. Inactive account → blocked with a "deactivated" message AND
 *      counts as a failed-login attempt against the per-account lockout
 *      (the audit pinned this — pretending the account doesn't exist
 *      would let an attacker enumerate active vs inactive emails).
 *   4. Five wrong passwords in a row → DB-backed lockout activates,
 *      `users.locked_until` is set 15 minutes in the future, and even a
 *      *correct* password during the lockout window is refused.
 *
 * The DB-backed lockout is independent of the cache-keyed IP throttle —
 * it survives cache flushes and follows the account, not the source IP.
 */
class LoginTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();

        // Laravel's ThrottlesLogins (used by AuthenticatesUsers in
        // LoginController) is cache-keyed, and the array cache driver is a
        // process-level singleton that survives across tests in the same
        // PHPUnit run. Without this flush, attempts in earlier login tests
        // accumulate against the rate limiter and the 5-attempt lockout
        // test trips the IP throttle (429) before reaching the per-account
        // recordFailedLogin() that engages the DB-backed lock.
        Cache::flush();
    }

    public function test_valid_credentials_authenticate_the_user_and_stash_account_id(): void
    {
        $user = User::factory()->create([
            'email' => 'alice@example.com',
            'password' => Hash::make('correct-horse'),
            'active' => 1,
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => 'alice@example.com',
            'password' => 'correct-horse',
        ]);

        $this->assertTrue(Auth::check());
        $this->assertSame($user->id, Auth::id());
        // LoginController::authenticated() stashes account_id into session
        // for downstream tenant-scoped queries. Pin that side-effect.
        $this->assertSame($user->account_id, session('account_id'));
        $response->assertRedirect();
    }

    public function test_invalid_password_does_not_authenticate(): void
    {
        User::factory()->create([
            'email' => 'alice@example.com',
            'password' => Hash::make('correct-horse'),
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => 'alice@example.com',
            'password' => 'wrong-password',
        ]);

        $this->assertFalse(Auth::check());
        $response->assertRedirect('/login');
        $response->assertSessionHas('error');
    }

    public function test_inactive_user_is_blocked_even_with_a_correct_password(): void
    {
        User::factory()->create([
            'email' => 'disabled@example.com',
            'password' => Hash::make('correct-horse'),
            'active' => 0,
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => 'disabled@example.com',
            'password' => 'correct-horse',
        ]);

        $this->assertFalse(Auth::check());
        $response->assertRedirect('/login');
        $response->assertSessionHas('error');
    }

    public function test_inactive_login_attempt_records_a_failed_login_against_the_account(): void
    {
        // Pin the audit decision: an inactive-account login attempt counts
        // as a failed attempt for the lockout machinery. Otherwise an
        // attacker could enumerate active emails by observing whether the
        // failed-counter ticked.
        $user = User::factory()->create([
            'email' => 'disabled@example.com',
            'password' => Hash::make('correct-horse'),
            'active' => 0,
            'failed_login_attempts' => 0,
        ]);

        $this->post('/login', [
            'email' => 'disabled@example.com',
            'password' => 'correct-horse',
        ]);

        $this->assertSame(1, (int) $user->fresh()->failed_login_attempts);
    }

    public function test_five_failed_attempts_lock_the_account_for_fifteen_minutes(): void
    {
        $user = User::factory()->create([
            'email' => 'lockme@example.com',
            'password' => Hash::make('correct-horse'),
            'failed_login_attempts' => 0,
        ]);

        // Five wrong attempts. The fifth should set locked_until to
        // now()+15min and zero the counter.
        for ($i = 0; $i < User::MAX_FAILED_LOGIN_ATTEMPTS; $i++) {
            $this->post('/login', [
                'email' => 'lockme@example.com',
                'password' => 'wrong',
            ]);
        }

        $reloaded = $user->fresh();
        $this->assertNotNull(
            $reloaded->locked_until,
            'After MAX_FAILED_LOGIN_ATTEMPTS the per-account lockout column must be set.'
        );
        $this->assertTrue(
            $reloaded->locked_until->isFuture(),
            'locked_until must point to a future timestamp so isLocked() returns true.'
        );
        $this->assertSame(
            0,
            (int) $reloaded->failed_login_attempts,
            'Once a lock is engaged the counter resets to 0 — the lock itself is the gate.'
        );
    }

    public function test_correct_password_during_active_lockout_is_still_rejected(): void
    {
        // Plant a future lockout directly on the row. The login controller's
        // very first check after credential validation is `isLocked()`, and
        // it must short-circuit before any auth attempt.
        $user = User::factory()->create([
            'email' => 'locked@example.com',
            'password' => Hash::make('correct-horse'),
            'locked_until' => now()->addMinutes(10),
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => 'locked@example.com',
            'password' => 'correct-horse',
        ]);

        $this->assertFalse(Auth::check());
        $response->assertRedirect('/login');
        $response->assertSessionHas('error');
    }

    public function test_successful_login_clears_a_stale_failed_attempt_counter(): void
    {
        // The counter sticks around between logins (as a tally) until a
        // successful authentication zeros it. Pin that reset.
        $user = User::factory()->create([
            'email' => 'almost@example.com',
            'password' => Hash::make('correct-horse'),
            'failed_login_attempts' => 3,
        ]);

        $this->post('/login', [
            'email' => 'almost@example.com',
            'password' => 'correct-horse',
        ]);

        $this->assertTrue(Auth::check());
        $this->assertSame(0, (int) $user->fresh()->failed_login_attempts);
    }

    public function test_four_failed_attempts_do_not_trigger_lockout(): void
    {
        // Boundary: threshold is 5. Four wrong attempts should increment
        // the counter but NOT set locked_until.
        $user = User::factory()->create([
            'email' => 'boundary@example.com',
            'password' => Hash::make('correct-horse'),
            'failed_login_attempts' => 0,
        ]);

        for ($i = 0; $i < User::MAX_FAILED_LOGIN_ATTEMPTS - 1; $i++) {
            $this->post('/login', [
                'email' => 'boundary@example.com',
                'password' => 'wrong',
            ]);
        }

        $reloaded = $user->fresh();
        $this->assertNull(
            $reloaded->locked_until,
            'Four attempts must NOT trigger the lockout.'
        );
        $this->assertSame(
            User::MAX_FAILED_LOGIN_ATTEMPTS - 1,
            (int) $reloaded->failed_login_attempts,
        );
    }

    public function test_lockout_expires_and_allows_login_again(): void
    {
        // Plant a lockout that expired one minute ago — the user must be
        // able to log in again.
        $user = User::factory()->create([
            'email' => 'expired@example.com',
            'password' => Hash::make('correct-horse'),
            'locked_until' => now()->subMinute(),
            'failed_login_attempts' => 0,
        ]);

        $this->post('/login', [
            'email' => 'expired@example.com',
            'password' => 'correct-horse',
        ]);

        $this->assertTrue(Auth::check());
    }
}
