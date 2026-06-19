<?php

declare(strict_types=1);

namespace Tests\Feature\Permissions;

use App\Models\User;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Pins the Settings-list gate found in the 2026-06-19 QA (#15). The admin
 * `settings/datatable` endpoint returned org configuration (pricing/tax/system
 * settings) with NO permission check, while the Settings page (index) and every
 * write path are gated on `settings_manage`. The fix gates the list on the same
 * slug. Teeth: drop the gate and "requires settings_manage" goes red.
 */
class SettingsListGateTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
    }

    private function actAs(array $perms): void
    {
        foreach ($perms as $p) {
            $this->createPermission($p);
        }
        $role = $this->createRole('set_'.uniqid());
        if ($perms !== []) {
            $role->givePermissionTo($perms);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::factory()->create();
        $this->assignRoleWithPivot($user, $role);
        $this->actingAs($user);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_settings_list_requires_settings_manage(): void
    {
        $this->createPermission('settings_manage'); // exists, but NOT granted
        $this->actAs([]);

        $this->postJson('/api/settings/datatable', [])->assertStatus(403);
    }

    public function test_settings_manage_holder_reaches_the_list(): void
    {
        $this->actAs(['settings_manage']);

        $response = $this->postJson('/api/settings/datatable', []);

        $this->assertNotSame(403, $response->status(), 'a settings_manage holder must reach the settings list');
    }
}
