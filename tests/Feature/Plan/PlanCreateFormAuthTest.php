<?php

declare(strict_types=1);

namespace Tests\Feature\Plan;

use App\Models\User;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * GET /api/plans/{id}/createplan loads the shared plan FORM DATA (discounts,
 * payment modes, locations) for BOTH the create and the EDIT dialogs. It was
 * gated create-only, so an edit-permitted operator (plans.edit, no
 * plans.create) got a 403 while editing a plan — the SPA then fell back to an
 * empty discount catalog and every service showed "No discounts available".
 * Pins that plans.edit OR plans.create may load it, and neither is 403.
 */
class PlanCreateFormAuthTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
    }

    /** Act as a NON-super-admin user holding exactly the given permissions. */
    private function actingWithPermissions(array $permissions): User
    {
        foreach ($permissions as $perm) {
            $this->createPermission($perm);
        }
        $role = $this->createRole('plan-form-test-'.uniqid());
        $role->givePermissionTo($permissions);

        $actor = User::factory()->create(['account_id' => 1]);
        $this->assignRoleWithPivot($actor, $role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($actor);

        return $actor;
    }

    public function test_edit_only_user_can_load_the_plan_create_form(): void
    {
        // The regression: pre-fix this returned 403 for an edit-only operator.
        $this->actingWithPermissions(['plans.edit']);

        $this->getJson('/api/plans/0/createplan')->assertOk();
    }

    public function test_create_user_can_load_the_plan_create_form(): void
    {
        $this->actingWithPermissions(['plans.create']);

        $this->getJson('/api/plans/0/createplan')->assertOk();
    }

    public function test_user_with_neither_plan_permission_is_forbidden(): void
    {
        // Holds an unrelated plans permission but not create/edit.
        $this->actingWithPermissions(['plans.list.view']);

        $this->getJson('/api/plans/0/createplan')->assertStatus(403);
    }
}
