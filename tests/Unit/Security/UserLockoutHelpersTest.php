<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Models\User;
use Illuminate\Support\Carbon;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Regression tests for the User-level helpers added by H2 (account
 * lockout). These cover both the *pure* parts of the state machine
 * (`isLocked()` and the lockout policy constants) and the *mutating*
 * helpers (`recordFailedLogin()` / `recordSuccessfulLogin()`) that
 * drive the counter and lock columns.
 */
class UserLockoutHelpersTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
    }

    public function test_lockout_policy_constants_match_documented_values(): void
    {
        // The login UI surfaces these numbers ("locked for 15 minutes",
        // "5 attempts remaining") — if either constant drifts, copy on
        // the login page silently lies to the user.
        $this->assertSame(5, User::MAX_FAILED_LOGIN_ATTEMPTS);
        $this->assertSame(15, User::LOGIN_LOCKOUT_MINUTES);
    }

    public function test_unset_locked_until_means_not_locked(): void
    {
        $user = new User();
        $user->forceFill(['locked_until' => null]);

        $this->assertFalse($user->isLocked());
    }

    public function test_past_locked_until_means_not_locked(): void
    {
        // Lock has expired — the user can attempt login again.
        $user = new User();
        $user->forceFill(['locked_until' => Carbon::now()->subMinute()]);

        $this->assertFalse($user->isLocked());
    }

    public function test_future_locked_until_means_locked(): void
    {
        // Active lock — attempts must be blocked.
        $user = new User();
        $user->forceFill(['locked_until' => Carbon::now()->addMinutes(10)]);

        $this->assertTrue($user->isLocked());
    }

    // ── DB-backed mutation tests ───────────────────────────────────

    public function test_record_failed_login_increments_the_counter(): void
    {
        $user = User::factory()->create(['failed_login_attempts' => 0]);

        $user->recordFailedLogin();

        $this->assertSame(1, (int) $user->fresh()->failed_login_attempts);
    }

    public function test_record_failed_login_triggers_lockout_after_threshold(): void
    {
        // Plant the counter at threshold - 1 so the next call crosses it.
        $user = User::factory()->create([
            'failed_login_attempts' => User::MAX_FAILED_LOGIN_ATTEMPTS - 1,
        ]);

        $user->recordFailedLogin();

        $reloaded = $user->fresh();
        $this->assertNotNull($reloaded->locked_until);
        $this->assertTrue($reloaded->locked_until->isFuture());
        $this->assertSame(0, (int) $reloaded->failed_login_attempts,
            'Counter must reset to 0 once the lock engages.');
    }

    public function test_record_successful_login_resets_counter_and_clears_lock(): void
    {
        $user = User::factory()->create([
            'failed_login_attempts' => 3,
            'locked_until' => Carbon::now()->subMinute(), // stale lock
        ]);

        $user->recordSuccessfulLogin();

        $reloaded = $user->fresh();
        $this->assertSame(0, (int) $reloaded->failed_login_attempts);
        $this->assertNull($reloaded->locked_until);
    }

    public function test_four_failed_attempts_do_not_trigger_lockout(): void
    {
        // Boundary: threshold is 5, so 4 attempts must NOT lock.
        $user = User::factory()->create(['failed_login_attempts' => 0]);

        for ($i = 0; $i < User::MAX_FAILED_LOGIN_ATTEMPTS - 1; $i++) {
            $user->recordFailedLogin();
        }

        $reloaded = $user->fresh();
        $this->assertNull($reloaded->locked_until);
        $this->assertSame(
            User::MAX_FAILED_LOGIN_ATTEMPTS - 1,
            (int) $reloaded->failed_login_attempts,
        );
    }
}
