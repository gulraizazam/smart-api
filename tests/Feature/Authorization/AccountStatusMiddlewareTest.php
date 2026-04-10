<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * The CheckAccountStatus middleware (alias `checkAccount`) wraps every
 * authed admin route. Its job is the kill-switch for deactivated users:
 * if `users.active != 1`, the middleware logs the user out, flushes the
 * session, and redirects to /login.
 *
 * The audit verified the kill-switch is the canonical way to revoke
 * access — disabling a user MUST end any in-flight session at the next
 * request, not on next login. These tests pin that contract.
 */
class AccountStatusMiddlewareTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private const TEST_ROUTE = '/_test/account-status';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();

        Route::middleware(['web', 'auth', 'checkAccount'])
            ->get(self::TEST_ROUTE, fn () => response('ok', 200));
    }

    public function test_active_user_passes_through_the_account_status_check(): void
    {
        $user = User::factory()->create(['active' => 1]);
        $this->actingAs($user);

        $response = $this->get(self::TEST_ROUTE);

        $response->assertOk();
    }

    public function test_inactive_user_is_logged_out_and_redirected_to_login(): void
    {
        $user = User::factory()->create(['active' => 0]);
        $this->actingAs($user);

        $response = $this->get(self::TEST_ROUTE);

        $response->assertRedirect('/login');
        // Killing the in-flight session is the entire point of this
        // middleware — assert it actually happened.
        $this->assertFalse(Auth::check());
    }

    public function test_a_user_deactivated_mid_session_loses_access_on_next_request(): void
    {
        $user = User::factory()->create(['active' => 1]);
        $this->actingAs($user);

        // First request: still active, gets through.
        $this->get(self::TEST_ROUTE)->assertOk();

        // Admin disables the account out-of-band (e.g. via the user-management
        // screen). The session itself is unchanged.
        User::query()->whereKey($user->id)->update(['active' => 0]);

        // Re-authenticate the same identity to mimic a subsequent request
        // arriving with the existing session cookie.
        $this->actingAs($user->fresh());

        $this->get(self::TEST_ROUTE)->assertRedirect('/login');
        $this->assertFalse(Auth::check());
    }
}
