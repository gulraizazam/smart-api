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

    public function test_detail_passes_the_gate_when_user_has_leads_manage(): void
    {
        $perm = $this->createPermission('leads_manage');
        $role = $this->createRole('TestLeadViewer');
        $role->givePermissionTo($perm);

        $actor = User::factory()->create(['account_id' => 1]);
        $this->assignRoleWithPivot($actor, $role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($actor);

        $response = $this->getJson('/api/leads/detail/999999');

        // Gate passed → whatever happens next (404/500 "Lead not found",
        // or 200) it must NOT be an auth/authz rejection.
        $this->assertNotSame(401, $response->getStatusCode());
        $this->assertNotSame(403, $response->getStatusCode());
    }

    public function test_detail_passes_via_alias_bridge_with_only_the_dotted_slug(): void
    {
        // §5.3 cutover bridge: 2026_05_21_130000 deletes the legacy leads_* rows
        // and re-grants the dotted catalog, so a CSR/Consultant role ends up
        // holding ONLY `leads.detail.view` — yet the controller still gates on
        // legacy `leads_manage`. PermissionAliasMap + Gate::before must bridge it,
        // or every such role 403s at cutover.
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
            'A role holding only the dotted leads.detail.view must pass the legacy '
            . 'leads_manage gate via the alias bridge (§5.3) — else CSR/Consultant 403 at cutover.',
        );
    }
}
