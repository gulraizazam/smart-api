<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Password reset flow has two halves:
 *
 *   1. Forgot-password — POST /password/email with an email address.
 *      Issues a reset token, persists it (hashed) in `password_resets`,
 *      and dispatches the ResetPassword notification.
 *
 *   2. Reset — POST /password/reset with the token + new password.
 *      Validates the token against the stored hash, calls
 *      ResetPasswordController::resetPassword() which writes the new
 *      password and logs the user in.
 */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        Cache::flush(); // ThrottlesLogins-style limiters on the broker
    }

    public function test_request_dispatches_a_reset_notification_for_a_known_email(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'forgot@example.com',
        ]);

        $response = $this->post('/password/email', [
            'email' => 'forgot@example.com',
        ]);

        Notification::assertSentTo($user, ResetPassword::class);
        $response->assertSessionHas('success');
    }

    public function test_request_does_not_dispatch_for_an_unknown_email(): void
    {
        Notification::fake();

        $response = $this->post('/password/email', [
            'email' => 'nobody@example.com',
        ]);

        Notification::assertNothingSent();
        $response->assertSessionHas('error');
    }

    public function test_valid_token_resets_password_and_logs_user_in(): void
    {
        $user = User::factory()->create([
            'email' => 'reset-me@example.com',
            'password' => Hash::make('original'),
        ]);

        // Issue a real token via the broker — same path the controller uses.
        $token = Password::broker()->createToken($user);

        $response = $this->post('/password/reset', [
            'token' => $token,
            'email' => 'reset-me@example.com',
            'password' => 'new-strong-password',
            'password_confirmation' => 'new-strong-password',
        ]);

        $response->assertRedirect();
        $this->assertTrue(
            Hash::check('new-strong-password', $user->fresh()->password),
            'New password must be persisted (hashed) on the user row.'
        );
        $this->assertTrue(
            Auth::check(),
            'Stock reset flow logs the user in directly (no 2FA enrollment on this account).'
        );
        $this->assertSame($user->id, Auth::id());
    }

    public function test_invalid_token_does_not_reset_password(): void
    {
        $user = User::factory()->create([
            'email' => 'reset-me@example.com',
            'password' => Hash::make('original'),
        ]);

        // Plant a real token, then submit a wrong one.
        Password::broker()->createToken($user);

        $response = $this->post('/password/reset', [
            'token' => 'totally-fake-token',
            'email' => 'reset-me@example.com',
            'password' => 'attacker-password',
            'password_confirmation' => 'attacker-password',
        ]);

        $this->assertTrue(
            Hash::check('original', $user->fresh()->password),
            'Wrong token must NOT mutate the password.'
        );
        $this->assertFalse(Auth::check());
        $response->assertSessionHasErrors();
    }

    public function test_used_token_cannot_be_replayed(): void
    {
        // Single-use property: once a token has reset a password, the
        // password_resets row is deleted by the broker so the same token
        // cannot be replayed for a second password change.
        $user = User::factory()->create([
            'email' => 'once@example.com',
            'password' => Hash::make('original'),
        ]);

        $token = Password::broker()->createToken($user);

        // First reset succeeds.
        $this->post('/password/reset', [
            'token' => $token,
            'email' => 'once@example.com',
            'password' => 'first-new-password',
            'password_confirmation' => 'first-new-password',
        ]);
        Auth::logout();

        // Replay the same token for a second mutation.
        $this->post('/password/reset', [
            'token' => $token,
            'email' => 'once@example.com',
            'password' => 'second-new-password',
            'password_confirmation' => 'second-new-password',
        ]);

        $this->assertTrue(
            Hash::check('first-new-password', $user->fresh()->password),
            'A consumed reset token must NOT allow a second password change.'
        );
        $this->assertFalse(Hash::check('second-new-password', $user->fresh()->password));
    }
}
