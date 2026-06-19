<?php

declare(strict_types=1);

namespace Tests\Feature\Permissions;

use App\Http\Requests\Service\UpdateServiceStatusRequest;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Pins the two real granularity fixes from the 2026-06-19 QA (#20):
 *   1. Brand status was gated on the WRONG slug (product_active) → now brand_active.
 *   2. Service status was an over-grant — `activate || deactivate` let an
 *      activate-only role deactivate (and vice-versa) → now bound to the
 *      requested direction (the VoucherType/Membership/MembershipType toggles the
 *      QA also flagged are NOT changed: their controllers already re-check the
 *      exact direction, so they were never over-grants).
 *
 * Teeth: revert either fix and its case reddens.
 */
class GranularityGateTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
    }

    private function actingWith(array $perms): User
    {
        foreach ($perms as $p) {
            $this->createPermission($p);
        }
        $role = $this->createRole('gran_'.uniqid());
        if ($perms !== []) {
            $role->givePermissionTo($perms);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::factory()->create();
        $this->assignRoleWithPivot($user, $role);
        $this->actingAs($user);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    public function test_service_status_gate_is_bound_to_requested_direction(): void
    {
        $this->createPermission('services.deactivate'); // exists, NOT granted
        $this->actingWith(['services.activate']); // activate-only role

        $activate = UpdateServiceStatusRequest::create('/', 'POST', ['status' => 1]);
        $this->assertTrue($activate->authorize(), 'activate-holder may activate');

        $deactivate = UpdateServiceStatusRequest::create('/', 'POST', ['status' => 0]);
        $this->assertFalse($deactivate->authorize(), 'activate-only holder must NOT be able to deactivate');
    }

    public function test_brand_status_requires_brand_active_not_product_active(): void
    {
        $this->createPermission('brand_active');
        $this->actingWith(['product_active']); // old (wrong) slug only

        $this->postJson('/api/brands/status', ['id' => 1, 'status' => 0])->assertStatus(401);
    }

    public function test_brand_active_holder_passes_the_status_gate(): void
    {
        $this->actingWith(['brand_active']);

        $response = $this->postJson('/api/brands/status', ['id' => 1, 'status' => 0]);

        $this->assertNotSame(401, $response->status(), 'a brand_active holder must pass the brand status gate');
    }
}
