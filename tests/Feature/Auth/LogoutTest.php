<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * LoginController::logout must:
 *   - call Filters::remove_filters() to drop any session-stored datatable
 *     filters (audit-defended state-leak vector — a logged-out admin's
 *     filters used to leak into the next login on shared kiosks)
 *   - end the auth session via guard->logout()
 *   - invalidate the underlying session
 *   - regenerate the CSRF token
 *
 * The end-to-end pin: after a logout request, Auth::check() is false and
 * accessing an authed route bounces back to /login.
 */
class LogoutTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
    }

    public function test_logout_terminates_the_session_and_redirects(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->assertTrue(Auth::check());

        $response = $this->post('/logout');

        $this->assertFalse(Auth::check());
        $response->assertRedirect('/');
    }

    public function test_logout_clears_persisted_filter_session_state(): void
    {
        // Plant a filter into the session under the key Filters helper uses,
        // then verify logout removes it. The exact key is opaque to the
        // outside world; what matters is that no filter-shaped session keys
        // survive the logout.
        $user = $this->actingAsAdmin();

        session(['filter_appointments' => ['status' => 'arrived']]);
        $this->assertTrue(session()->has('filter_appointments'));

        $this->post('/logout');

        $this->assertFalse(
            session()->has('filter_appointments'),
            'Filters::remove_filters must wipe persisted filter state on logout — '
            .'previously, kiosk-style shared workstations leaked filters between users.'
        );
    }

    public function test_logout_response_status_is_302_redirect_for_browser_clients(): void
    {
        $this->actingAsAdmin();
        $response = $this->post('/logout');
        $response->assertStatus(302);
    }

    public function test_logout_response_is_204_no_content_for_json_clients(): void
    {
        // The controller branches on $request->wantsJson(). Pin both arms.
        $this->actingAsAdmin();
        $response = $this->postJson('/logout');
        $response->assertStatus(204);
    }
}
