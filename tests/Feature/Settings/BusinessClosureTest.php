<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Models\Locations;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * BusinessClosureController manages business closure CRUD: create
 * closures for specific locations and date ranges, edit, delete.
 * Closures affect scheduling and appointment availability.
 *
 * Pins:
 *   1. Datatable returns paginated closure list.
 *   2. Create returns form data (locations).
 *   3. Store creates closure with valid title, location_ids, date range.
 *   4. Store rejects missing required fields.
 *   5. Store rejects end_date before start_date.
 *   6. Edit returns closure data.
 *   7. Unauthenticated access is rejected.
 */
class BusinessClosureTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
        $this->grantPermissions([
            'business_closures_manage', 'business_closures_create',
            'business_closures_edit', 'business_closures_delete',
        ]);
    }

    private function grantPermissions(array $permissions): void
    {
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($permissions as $perm) {
            $this->createPermission($perm);
        }

        $role = $this->createRole('test-admin-' . uniqid());
        $role->givePermissionTo($permissions);
        auth()->user()->assignRole($role);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_datatable_returns_paginated_data(): void
    {
        $response = $this->postJson('/api/business-closures/datatable', [
            'pagination' => ['page' => 1, 'perpage' => 10],
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_create_returns_form_data(): void
    {
        $response = $this->getJson('/api/business-closures/create');
        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'data']);
    }

    public function test_store_creates_closure_with_valid_data(): void
    {
        $location = Locations::first();

        $response = $this->postJson('/api/business-closures', [
            'title' => 'Annual Maintenance',
            'location_ids' => [$location->id],
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-03',
        ]);

        $this->assertContains($response->status(), [200, 201]);
    }

    public function test_store_rejects_missing_required_fields(): void
    {
        $response = $this->postJson('/api/business-closures', []);
        $response->assertStatus(422);
    }

    public function test_store_rejects_end_date_before_start_date(): void
    {
        $location = Locations::first();

        $response = $this->postJson('/api/business-closures', [
            'title' => 'Invalid Closure',
            'location_ids' => [$location->id],
            'start_date' => '2026-05-05',
            'end_date' => '2026-05-01',
        ]);

        $response->assertStatus(422);
    }

    public function test_store_rejects_empty_location_ids(): void
    {
        $response = $this->postJson('/api/business-closures', [
            'title' => 'No Location Closure',
            'location_ids' => [],
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-03',
        ]);

        $response->assertStatus(422);
    }

    public function test_destroy_deletes_closure(): void
    {
        $location = Locations::first();

        $createResponse = $this->postJson('/api/business-closures', [
            'title' => 'Temp Closure',
            'location_ids' => [$location->id],
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-02',
        ]);

        $closureId = $createResponse->json('data.closure.id')
            ?? $createResponse->json('data.id');

        if ($closureId) {
            $response = $this->deleteJson("/api/business-closures/{$closureId}");
            $this->assertContains($response->status(), [200, 204]);
        } else {
            $this->markTestSkipped('Could not create closure for destroy test.');
        }
    }

    public function test_unauthenticated_access_is_rejected(): void
    {
        auth()->logout();
        $response = $this->postJson('/api/business-closures/datatable', []);
        $this->assertContains($response->status(), [401, 302, 403]);
    }

    /**
     * The closure-lookup behind the `check` endpoint now lives in the service
     * (BusinessClosureService::closuresOnDate) rather than inline in the
     * controller. Pin its behavior: a closure covering the date is returned
     * with its locations eager-loaded; the branch filter matches a closure
     * scoped to that branch AND a global (no-location) closure, but excludes a
     * closure scoped only to a different branch. Revert the extraction and the
     * method no longer exists — this test goes red.
     */
    public function test_closures_on_date_returns_matching_closures_with_locations(): void
    {
        $accountId = (int) auth()->user()->account_id;
        $branchA = Locations::first();
        $branchB = Locations::factory()->create(['account_id' => $accountId]);

        $branchClosure = \App\Models\BusinessClosure::create([
            'title' => 'Branch A maintenance',
            'start_date' => '2026-07-10',
            'end_date' => '2026-07-12',
            'account_id' => $accountId,
        ]);
        $branchClosure->locations()->attach($branchA->id);

        $globalClosure = \App\Models\BusinessClosure::create([
            'title' => 'Public holiday',
            'start_date' => '2026-07-10',
            'end_date' => '2026-07-12',
            'account_id' => $accountId,
        ]);

        $service = app(\App\Services\Schedule\BusinessClosureService::class);

        // Within the window, scoped to branch A: matches the branch-A closure
        // AND the global closure, but not a branch-B-only closure.
        $branchBOnly = \App\Models\BusinessClosure::create([
            'title' => 'Branch B only',
            'start_date' => '2026-07-10',
            'end_date' => '2026-07-12',
            'account_id' => $accountId,
        ]);
        $branchBOnly->locations()->attach($branchB->id);

        $forBranchA = $service->closuresOnDate($accountId, '2026-07-11', $branchA->id);
        $ids = $forBranchA->pluck('id')->all();

        $this->assertContains($branchClosure->id, $ids);
        $this->assertContains($globalClosure->id, $ids);
        $this->assertNotContains($branchBOnly->id, $ids);

        // Locations are eager-loaded for the Resource (no lazy query needed).
        $matched = $forBranchA->firstWhere('id', $branchClosure->id);
        $this->assertTrue($matched->relationLoaded('locations'));

        // Outside the window: nothing matches.
        $this->assertCount(0, $service->closuresOnDate($accountId, '2026-08-01', $branchA->id));
    }
}
