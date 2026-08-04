<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\LeadDepartment;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Pins the LeadDepartment CRUD contract.
 *
 * Regression guard: the CRUD is the source of truth for the Marketing
 * dashboard's Department split panel, so anything that breaks tenant
 * isolation here silently poisons the dashboard for every downstream
 * viewer. Every test either asserts (a) a role without
 * `leads.departments.manage` is 403'd, or (b) a caller in account A can
 * never see/touch a row in account B.
 */
class LeadDepartmentControllerTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_index_returns_403_without_manage_permission(): void
    {
        $user = User::factory()->create(['account_id' => 1]);
        $this->actingAs($user);

        $response = $this->getJson('/api/lead-departments');

        $response->assertStatus(403);
        // Never 401 — the SPA treats 401 as session expiry and signs the user out.
        $this->assertNotSame(401, $response->getStatusCode());
    }

    public function test_index_returns_paginated_departments_for_authorized_user(): void
    {
        $user = $this->userWithManage(accountId: 1);
        $this->actingAs($user);

        LeadDepartment::create(['name' => 'Skin', 'account_id' => 1]);
        LeadDepartment::create(['name' => 'Hair', 'account_id' => 1]);
        LeadDepartment::create(['name' => 'Foreign', 'account_id' => 999]); // other tenant

        $response = $this->getJson('/api/lead-departments');

        $response->assertOk();
        $names = collect($response->json('data.items'))->pluck('name')->all();
        $this->assertContains('Skin', $names);
        $this->assertContains('Hair', $names);
        $this->assertNotContains('Foreign', $names, 'tenant isolation broken — foreign account rows leaked');
    }

    public function test_store_creates_department_scoped_to_current_tenant(): void
    {
        $user = $this->userWithManage(accountId: 42);
        $this->actingAs($user);

        $response = $this->postJson('/api/lead-departments', [
            'name' => 'Weight Loss',
            'sort_order' => 5,
            'active' => true,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('lead_departments', [
            'name' => 'Weight Loss',
            'account_id' => 42, // NOT the requester's guess of some other account
            'sort_order' => 5,
        ]);
    }

    public function test_store_rejects_duplicate_name_within_same_tenant(): void
    {
        $user = $this->userWithManage(accountId: 1);
        $this->actingAs($user);
        LeadDepartment::create(['name' => 'Skin', 'account_id' => 1]);

        $response = $this->postJson('/api/lead-departments', ['name' => 'Skin']);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_store_allows_same_name_across_different_tenants(): void
    {
        // Tenant A already has "Skin"
        LeadDepartment::create(['name' => 'Skin', 'account_id' => 1]);

        // Tenant B (via another user) can still create "Skin"
        $userB = $this->userWithManage(accountId: 2);
        $this->actingAs($userB);
        $response = $this->postJson('/api/lead-departments', ['name' => 'Skin']);

        $response->assertOk();
        $this->assertDatabaseHas('lead_departments', ['name' => 'Skin', 'account_id' => 2]);
    }

    public function test_update_404s_when_targeting_foreign_tenant(): void
    {
        $foreign = LeadDepartment::create(['name' => 'Foreign', 'account_id' => 999]);
        $user = $this->userWithManage(accountId: 1);
        $this->actingAs($user);

        $response = $this->patchJson("/api/lead-departments/{$foreign->id}", [
            'name' => 'Hijacked',
        ]);

        $response->assertStatus(404);
        $this->assertDatabaseHas('lead_departments', ['id' => $foreign->id, 'name' => 'Foreign']);
    }

    public function test_destroy_soft_deletes(): void
    {
        $user = $this->userWithManage(accountId: 1);
        $this->actingAs($user);
        $dept = LeadDepartment::create(['name' => 'Temp', 'account_id' => 1]);

        $this->deleteJson("/api/lead-departments/{$dept->id}")->assertOk();

        // Row still in DB but soft-deleted.
        $row = LeadDepartment::withTrashed()->find($dept->id);
        $this->assertNotNull($row);
        $this->assertNotNull($row->deleted_at);
    }

    // ------------------------------------------------------------------

    private function userWithManage(int $accountId): User
    {
        $user = User::factory()->create(['account_id' => $accountId]);
        $perm = $this->createPermission('leads.departments.manage');
        $role = $this->createRole('LeadDeptManagers-'.$user->id);
        $role->givePermissionTo($perm);
        $this->assignRoleWithPivot($user, $role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }
}
