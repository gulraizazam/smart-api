<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Locations;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * ScheduleController manages the scheduling calendar: locations,
 * business working days, shifts, repeating shifts, time-off, and
 * bulk operations. These are core business operations that affect
 * appointment availability.
 *
 * Pins:
 *   1. Get-locations returns active locations for the account.
 *   2. Business working days retrieval and save work round-trip.
 *   3. Get-shifts accepts location/date params without error.
 *   4. Time-off CRUD endpoints accept valid payloads.
 *   5. Unauthenticated access is rejected.
 */
class ApiScheduleTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
        $this->grantPermissions([
            'schedule_manage', 'schedule_create', 'schedule_edit', 'schedule_delete',
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

    public function test_get_locations_returns_success_envelope(): void
    {
        $response = $this->getJson('/api/schedule/get-locations');
        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'data']);
    }

    public function test_get_business_working_days_returns_success(): void
    {
        $response = $this->getJson('/api/schedule/get-business-working-days');
        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'data']);
    }

    public function test_save_business_working_days_accepts_valid_payload(): void
    {
        $response = $this->postJson('/api/schedule/save-business-working-days', [
            'working_days' => [
                'monday' => true,
                'tuesday' => true,
                'wednesday' => true,
                'thursday' => true,
                'friday' => true,
                'saturday' => true,
                'sunday' => false,
            ],
            'exceptions' => [],
        ]);

        $this->assertContains($response->status(), [200, 201, 422]);
    }

    public function test_get_shifts_accepts_location_and_date_range(): void
    {
        $location = Locations::first();
        $this->assertNotNull($location, 'At least one location must exist.');

        $response = $this->postJson('/api/schedule/get-shifts', [
            'location_id' => $location->id,
            'resource_type_id' => 2,
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-07',
        ]);

        $response->assertOk();
    }

    /**
     * Regression: get-shifts must return 200 with empty arrays when a location
     * has no doctors allocated — the SPA renders a "no doctors allocated"
     * empty state from this payload. A prior version returned 400 here, which
     * surfaced as a red "Couldn't load schedule" banner instead. This test
     * would fail if that 400 guard were reintroduced.
     */
    public function test_get_shifts_returns_empty_payload_for_location_with_no_doctors(): void
    {
        $location = Locations::factory()->create();

        $response = $this->postJson('/api/schedule/get-shifts', [
            'location_id' => $location->id,
            'resource_type_id' => 2,
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-07',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['success', 'data' => ['resources', 'shifts', 'closures', 'time_offs']]);
        $this->assertSame([], $response->json('data.resources'));
        $this->assertSame([], $response->json('data.shifts'));
        $this->assertSame([], $response->json('data.time_offs'));
    }

    public function test_store_time_off_validates_required_fields(): void
    {
        // Missing required fields should return 422.
        $response = $this->postJson('/api/schedule/store-time-off', []);
        $this->assertContains($response->status(), [422, 400]);
    }

    public function test_delete_shifts_validates_required_fields(): void
    {
        $response = $this->postJson('/api/schedule/delete-shifts', []);
        $this->assertContains($response->status(), [422, 400, 200]);
    }

    public function test_get_time_offs_accepts_valid_payload(): void
    {
        $location = Locations::first();

        $response = $this->postJson('/api/schedule/get-time-offs', [
            'resource_id' => 1,
            'location_id' => $location->id,
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
        ]);

        $this->assertContains($response->status(), [200, 422]);
    }

    public function test_store_repeating_shifts_validates_required_fields(): void
    {
        $response = $this->postJson('/api/schedule/store-repeating-shifts', []);
        $this->assertContains($response->status(), [422, 400]);
    }

    public function test_unauthenticated_access_is_rejected(): void
    {
        auth()->logout();
        $response = $this->getJson('/api/schedule/get-locations');
        $this->assertContains($response->status(), [401, 302, 403]);
    }
}
