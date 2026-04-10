<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * The CheckIpRestriction middleware enforces an allow-list of source IPs
 * for sensitive roles (typically Finance, HRM, Administrator). It reads
 * `config('ip_restrictions.restricted_roles')` and
 * `config('ip_restrictions.allowed_ips')`. A user holding a restricted
 * role from any other IP is logged out and redirected.
 *
 * The audit added this as belt-and-braces around the 2FA control: even
 * with stolen credentials and a valid TOTP code, an attacker has to also
 * be on the corporate network to land in those high-blast-radius roles.
 */
class IpRestrictionMiddlewareTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private const TEST_ROUTE = '/_test/ip-restricted';

    private const ALLOWED_IP = '203.0.113.42';

    private const BLOCKED_IP = '198.51.100.7';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();

        // Pin the IP-restriction config for this test only. The middleware
        // reads role names from config and compares against the user's
        // first Spatie role, so the role name has to match what the
        // production config lists.
        Config::set('ip_restrictions.restricted_roles', ['Finance']);
        Config::set('ip_restrictions.allowed_ips', [self::ALLOWED_IP]);

        Route::middleware(['web', 'auth', 'check.ip.restriction'])
            ->get(self::TEST_ROUTE, fn () => response('ok', 200));

        // Ensure the role exists. Test isolation: refresh Spatie cache.
        // The TestCase::createRole helper sidesteps the production-only
        // `roles.commission` NOT NULL column that Spatie's stock model
        // doesn't fill on its own.
        $this->createRole('Finance');
        $this->createRole('Receptionist');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_unrestricted_role_is_not_subject_to_ip_check(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Receptionist');
        $this->actingAs($user);

        $response = $this->get(self::TEST_ROUTE, ['REMOTE_ADDR' => self::BLOCKED_IP]);

        $response->assertOk();
    }

    public function test_restricted_role_from_an_allowed_ip_passes_through(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Finance');
        $this->actingAs($user);

        $response = $this->call(
            'GET',
            self::TEST_ROUTE,
            [],
            [],
            [],
            ['REMOTE_ADDR' => self::ALLOWED_IP]
        );

        $response->assertOk();
        $this->assertTrue(Auth::check());
    }

    public function test_restricted_role_from_a_blocked_ip_is_logged_out_and_redirected(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Finance');
        $this->actingAs($user);

        $response = $this->call(
            'GET',
            self::TEST_ROUTE,
            [],
            [],
            [],
            ['REMOTE_ADDR' => self::BLOCKED_IP]
        );

        $response->assertRedirect('unauthorized');
        $this->assertFalse(
            Auth::check(),
            'CheckIpRestriction must call Auth::logout() when blocking — leaving the session attached '
            .'would let a subsequent allowed-IP request slip through with the same cookie.'
        );
    }
}
