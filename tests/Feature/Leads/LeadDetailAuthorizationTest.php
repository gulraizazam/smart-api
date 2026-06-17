<?php

declare(strict_types=1);

namespace Tests\Feature\Leads;

use App\Models\User;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Pins the HTTP-status contract of GET /api/leads/detail/{id}.
 *
 * Regression: the controller returned 401 when the caller lacked
 * `leads_manage`. The SPA (`api.ts`) treats EVERY 401 as session
 * expiry → dispatches `auth:expired` → signs the user out. So an FDM
 * user (who can see the leads list but not lead detail) was kicked to
 * the login screen the moment they clicked a lead. Authorization
 * failures must be 403 (authenticated but forbidden), never 401.
 */
class LeadDetailAuthorizationTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_detail_returns_403_not_401_when_user_lacks_leads_manage(): void
    {
        $actor = User::factory()->create(['account_id' => 1]);
        $this->actingAs($actor);

        $response = $this->getJson('/api/leads/detail/1');

        $response->assertStatus(403);
        // The crux: a 401 here is what logs the SPA out. Guard it explicitly.
        $this->assertNotSame(
            401,
            $response->getStatusCode(),
            'leads/detail must not 401 on an authorization failure — that signs the SPA user out.',
        );
    }

    public function test_detail_403_for_legacy_only_role_now_that_the_bridge_is_gone(): void
    {
        // Tier P P3 removed the legacy<->dotted alias bridge. A role holding ONLY
        // the legacy `leads_manage` slug must now 403 on the dotted-gated endpoint
        // (`leads.detail.view`) — the behavioural pin that the bridge is truly gone:
        // it goes red the instant any Gate::before alias hook is reintroduced.
        $perm = $this->createPermission('leads_manage');
        $role = $this->createRole('TestLegacyOnlyLeadViewer');
        $role->givePermissionTo($perm);

        $actor = User::factory()->create(['account_id' => 1]);
        $this->assignRoleWithPivot($actor, $role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($actor);

        $response = $this->getJson('/api/leads/detail/999999');

        $response->assertStatus(403);
    }

    public function test_detail_passes_with_the_dotted_slug(): void
    {
        // crm3's catalog grants the dotted `leads.detail.view`; the controller
        // gates on it directly (P2 repointed it, P3 removed the bridge). A role
        // holding the dotted slug passes with no alias indirection.
        $perm = $this->createPermission('leads.detail.view');
        $role = $this->createRole('DottedOnlyLeadViewer');
        $role->givePermissionTo($perm);

        $actor = User::factory()->create(['account_id' => 1]);
        $this->assignRoleWithPivot($actor, $role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($actor);

        $response = $this->getJson('/api/leads/detail/999999');

        $this->assertNotSame(401, $response->getStatusCode());
        $this->assertNotSame(
            403,
            $response->getStatusCode(),
            'A role holding the dotted leads.detail.view must pass the gate directly.',
        );
    }
}
