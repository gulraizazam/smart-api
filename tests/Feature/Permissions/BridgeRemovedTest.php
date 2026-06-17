<?php

declare(strict_types=1);

namespace Tests\Feature\Permissions;

use App\Models\User;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Tier P — P3: the legacy<->dotted alias bridge is REMOVED.
 *
 * Pins all three pieces of the removal so none can silently come back:
 *   1. App\Support\PermissionAliasMap no longer exists.
 *   2. Gate::before no longer bridges — a role holding only a legacy slug does
 *      NOT pass the dotted gate it used to satisfy via the bridge.
 *   3. User::toAuthPayload no longer expands a grant into its legacy aliases.
 *
 * P1 (orphan backfill) + P2 (gate repoints) + the P3 contact backfill guarantee
 * every prod role holds the dotted slug it needs, so removing the bridge causes
 * no access loss — these tests pin the mechanism, not the prod grant state.
 */
class BridgeRemovedTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_permission_alias_map_file_is_gone(): void
    {
        // File check, not class_exists() — a stale optimized autoload classmap can
        // still resolve a deleted class. The file being gone is the real pin.
        $this->assertFileDoesNotExist(
            base_path('app/Support/PermissionAliasMap.php'),
            'PermissionAliasMap must be deleted in P3 — the bridge is retired.',
        );
    }

    public function test_gate_no_longer_bridges_legacy_to_dotted(): void
    {
        // A non-super-admin role holding ONLY the legacy slug must NOT pass the
        // dotted gate. With the bridge gone there is no Gate::before alias hook
        // to satisfy it — this is what lets us drop the duplicate legacy rows.
        $this->createPermission('leads_manage');
        $this->createPermission('leads.detail.view');
        $role = $this->createRole('LegacyOnly');
        $role->givePermissionTo('leads_manage');

        $user = User::factory()->create(['account_id' => 1]);
        $this->assignRoleWithPivot($user, $role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertTrue($user->can('leads_manage'), 'holds the legacy slug directly');
        $this->assertFalse(
            $user->can('leads.detail.view'),
            'a legacy-only role must NOT pass the dotted gate once the bridge is removed',
        );
    }

    public function test_auth_payload_does_not_expand_into_legacy_aliases(): void
    {
        // A role holding only the dotted slug must surface ONLY the dotted slug in
        // the SPA permission payload — the legacy alias must not be injected.
        $this->createPermission('leads.detail.view');
        $role = $this->createRole('DottedOnly');
        $role->givePermissionTo('leads.detail.view');

        $user = User::factory()->create(['account_id' => 1]);
        $this->assignRoleWithPivot($user, $role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = $user->fresh()->toAuthPayload()['permissions'];

        $this->assertContains('leads.detail.view', $permissions, 'the held dotted slug is present');
        $this->assertNotContains(
            'leads_manage',
            $permissions,
            'toAuthPayload must NOT expand the dotted grant into its legacy alias',
        );
    }
}
