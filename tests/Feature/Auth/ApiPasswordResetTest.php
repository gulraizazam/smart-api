<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * SPA-facing password reset flow:
 *   POST /api/password/email   { email }
 *   POST /api/password/reset   { email, token, password, password_confirmation }
 *
 * Two contracts that differ from the legacy Blade pair:
 *   1. /api/password/email always 200 — the response is identical
 *      whether the email matches a real account or not. The legacy
 *      Blade flow leaks "didn't find any record" which lets an
 *      attacker enumerate active accounts.
 *   2. The reset email URL is overridden to the SPA's /reset-password
 *      screen via AppServiceProvider::configurePasswordResetUrl, not
 *      the Blade route('password.reset') that dies at cutover.
 */
class ApiPasswordResetTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        // The route-level throttle (5/60min on /api/password/email) is
        // cache-keyed; flush so each test starts at zero.
        Cache::flush();

        // The broker reads `password_resets` (config/auth.php) — the table the
        // 2014 migration creates and the live DB actually has. An earlier
        // version of this setUp created `password_reset_tokens` instead, which
        // MASKED a production bug: config pointed the broker at a table that is
        // never created, so /api/password/* and the nightly `auth:clear-resets`
        // cron 500'd (SQLSTATE[42S02]) in prod. Fixed by repointing config to
        // `password_resets`. We still ensure the table exists here in case a
        // stale schema dump omitted it.
        if (! Schema::hasTable('password_resets')) {
            Schema::create('password_resets', function (Blueprint $table) {
                $table->string('email')->index();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    public function test_email_endpoint_rejects_invalid_email_shape(): void
    {
        $this->postJson('/api/password/email', ['email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->postJson('/api/password/email', [])
            ->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    public function test_email_endpoint_returns_generic_200_for_unknown_email(): void
    {
        // Enumeration defense: the response shape MUST be identical
        // whether the email exists or not. A future "fix" that surfaces
        // 'passwords.user' as 422 reintroduces the enumeration vector.
        Notification::fake();

        $response = $this->postJson('/api/password/email', [
            'email' => 'nobody@example.com',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'message' => 'If an account exists for that email, a reset link has been sent.',
        ]);
        Notification::assertNothingSent();
    }

    public function test_email_endpoint_returns_generic_200_for_known_email_and_dispatches_notification(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'forgot@example.com']);

        $response = $this->postJson('/api/password/email', [
            'email' => 'forgot@example.com',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'message' => 'If an account exists for that email, a reset link has been sent.',
        ]);
        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_reset_password_email_url_targets_spa_not_blade(): void
    {
        // Pin AppServiceProvider::configurePasswordResetUrl. With the
        // override stripped, this URL would be the Blade
        // route('password.reset', $token) — which dies at cutover and
        // would silently send dead links in production reset emails.
        $user = User::factory()->create(['email' => 'urltest@example.com']);
        $token = 'test-token-abc-123';

        $notification = new ResetPasswordNotification($token);
        $mail = $notification->toMail($user);

        $this->assertStringContainsString(
            '/admin-v2/reset-password/'.$token,
            (string) $mail->actionUrl,
            'Reset email action URL must target the SPA reset screen, '
            . 'not the legacy Blade route(\'password.reset\').',
        );
        $this->assertStringContainsString(
            'email='.urlencode('urltest@example.com'),
            (string) $mail->actionUrl,
            'Email must be appended as a query param so the SPA can prefill the form.',
        );
        // Belt-and-suspenders: the resolved URL must NOT contain the
        // Blade `/admin/` segment.
        $this->assertStringNotContainsString(
            '/admin/',
            (string) $mail->actionUrl,
            'A `/admin/` URL would resolve to the legacy Blade view that dies at cutover.',
        );
    }

    public function test_reset_endpoint_validates_required_fields(): void
    {
        // Missing token / email / password → 422 before the broker
        // even sees the request.
        $this->postJson('/api/password/reset', [])
            ->assertStatus(422);

        $this->postJson('/api/password/reset', [
            'email' => 'a@b.com',
            'token' => 'whatever',
        ])->assertStatus(422); // missing password
    }

    public function test_reset_endpoint_rejects_weak_password(): void
    {
        $this->postJson('/api/password/reset', [
            'email' => 'a@b.com',
            'token' => 'whatever',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertStatus(422);

        $this->postJson('/api/password/reset', [
            'email' => 'a@b.com',
            'token' => 'whatever',
            'password' => 'NoSymbol1234',
            'password_confirmation' => 'NoSymbol1234',
        ])->assertStatus(422);
    }

    public function test_reset_endpoint_rejects_mismatched_confirmation(): void
    {
        $this->postJson('/api/password/reset', [
            'email' => 'a@b.com',
            'token' => 'whatever',
            'password' => 'NewPass1!',
            'password_confirmation' => 'OtherPass1!',
        ])->assertStatus(422);
    }

    public function test_reset_endpoint_rejects_invalid_token_with_422(): void
    {
        // Pass a syntactically valid request but a token that the broker
        // doesn't have a record for. The broker returns INVALID_TOKEN
        // and our controller surfaces it as a 422 with the translated
        // message ('passwords.token').
        User::factory()->create(['email' => 'real@example.com']);

        $this->postJson('/api/password/reset', [
            'email' => 'real@example.com',
            'token' => str_repeat('0', 60), // valid string, not in DB
            'password' => 'NewPass1!',
            'password_confirmation' => 'NewPass1!',
        ])->assertStatus(422);
    }

    public function test_full_flow_resets_password_when_token_is_valid(): void
    {
        // End-to-end: ask the broker to mint a token, hand it to
        // /api/password/reset, verify the user can then sign in with
        // the new password.
        $user = User::factory()->create([
            'email' => 'flow@example.com',
            'password' => Hash::make('OldPass1!'),
        ]);

        $token = Password::broker()->createToken($user);

        $response = $this->postJson('/api/password/reset', [
            'email' => 'flow@example.com',
            'token' => $token,
            'password' => 'NewPass2!',
            'password_confirmation' => 'NewPass2!',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'message' => 'Password has been reset successfully.',
        ]);
        $this->assertTrue(
            Hash::check('NewPass2!', $user->fresh()->password),
            'Password hash on the row must reflect the new password.',
        );
    }

    public function test_full_flow_token_is_single_use(): void
    {
        // Tokens are consumed by the broker on successful reset. A
        // second reset with the same token must fail.
        $user = User::factory()->create([
            'email' => 'replay@example.com',
            'password' => Hash::make('OldPass1!'),
        ]);

        $token = Password::broker()->createToken($user);

        $this->postJson('/api/password/reset', [
            'email' => 'replay@example.com',
            'token' => $token,
            'password' => 'NewPass1!',
            'password_confirmation' => 'NewPass1!',
        ])->assertOk();

        // Replay → broker rejects, controller surfaces 422.
        $this->postJson('/api/password/reset', [
            'email' => 'replay@example.com',
            'token' => $token,
            'password' => 'AnotherPass2!',
            'password_confirmation' => 'AnotherPass2!',
        ])->assertStatus(422);
    }

    public function test_reset_url_override_is_gated_off_before_cutover(): void
    {
        // §5.2: pre-cutover (app.spa_cutover_done = false) AppServiceProvider must
        // NOT register the SPA reset-URL override, so emails keep Laravel's default
        // route('password.reset') Blade link — a working page for the still-live
        // Blade users. Overriding too early would mail dead SPA links to every
        // Blade user requesting a reset. (The post-cutover override is pinned by
        // test_reset_password_email_url_targets_spa_not_blade above.)
        $reflect = new \ReflectionMethod(\App\Providers\AppServiceProvider::class, 'configurePasswordResetUrl');
        $reflect->setAccessible(true);
        $provider = $this->app->getProvider(\App\Providers\AppServiceProvider::class);

        try {
            // Clear the boot-time override, flip the flag off, re-run the gate.
            ResetPasswordNotification::createUrlUsing(null);
            config(['app.spa_cutover_done' => false]);
            $reflect->invoke($provider);

            $this->assertNull(
                ResetPasswordNotification::$createUrlCallback,
                'Pre-cutover the SPA reset-URL override must NOT be registered.',
            );
        } finally {
            // Restore the post-cutover override so sibling tests are unaffected.
            ResetPasswordNotification::createUrlUsing(null);
            config(['app.spa_cutover_done' => true]);
            $reflect->invoke($provider);
        }
    }
}
