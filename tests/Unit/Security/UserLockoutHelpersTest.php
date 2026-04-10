<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Models\User;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Regression tests for the User-level helpers added by H2 (account
 * lockout). These cover the *pure* parts of the state machine —
 * `isLocked()` and the lockout policy constants — so we can pin the
 * contract without needing a database connection.
 *
 * The mutating helpers `recordFailedLogin()` and `recordSuccessfulLogin()`
 * touch the DB and were verified end-to-end via tinker smoke tests in the
 * H2 implementation session; they live outside the unit-test boundary.
 */
class UserLockoutHelpersTest extends TestCase
{
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

}
