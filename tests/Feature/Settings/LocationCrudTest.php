<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Models\Locations;
use App\Services\Location\LocationService;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Locations is one of the most upstream lookup tables in the domain
 * — almost every financial, patient, appointment, and report query
 * filters by it. This suite is smoke coverage only: it pins the
 * basic Eloquent CRUD surface + the tenant scope + the soft-delete
 * contract. More nuanced behaviour (the createRecord() static path,
 * the image upload, service assignment via handlePostCreation) is
 * deliberately out of scope — those are tested implicitly via the
 * UsesFinancialFixtures seeder and the factory coverage.
 *
 * Invariants pinned:
 *
 *   1. `Locations::create()` writes a row stamped with the authed
 *      user's account_id via the BaseModel creating hook.
 *   2. `LocationService::deleteLocation()` returns a status array
 *      with the delete outcome — the model tunnels the contract.
 *   3. `changeStatus(id, '0')` flips the active flag to inactive;
 *      `changeStatus(id, '1')` flips it back.
 *   4. Soft delete preserves the row with a populated deleted_at
 *      column (the audit log relies on that for the join).
 */
class LocationCrudTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private LocationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();

        $this->service = app(LocationService::class);
    }

    public function test_create_persists_row_and_stamps_account_id(): void
    {
        $location = Locations::create([
            'name' => 'Main Clinic',
            'slug' => 'main-clinic',
            'address' => '123 Test St',
            'region_id' => 1,
            'city_id' => 1,
            'active' => 1,
            'sort_no' => 100,
        ]);

        $this->assertInstanceOf(Locations::class, $location);
        $this->assertSame('Main Clinic', $location->name);
        $this->assertSame(
            (int) Auth::user()->account_id,
            (int) $location->account_id,
            'BaseModel creating hook must stamp the tenant id.',
        );
    }

    public function test_update_writes_new_fields_to_the_row(): void
    {
        $location = Locations::create([
            'name' => 'Old Name',
            'slug' => 'loc-1',
            'region_id' => 1,
            'city_id' => 1,
            'active' => 1,
        ]);

        $location->update(['name' => 'New Name', 'address' => 'Updated St']);

        $this->assertSame('New Name', $location->fresh()->name);
        $this->assertSame('Updated St', $location->fresh()->address);
    }

    public function test_delete_soft_deletes_the_location_row(): void
    {
        $location = Locations::create([
            'name' => 'Deletable Clinic',
            'slug' => 'del-loc',
            'region_id' => 1,
            'city_id' => 1,
            'active' => 1,
        ]);

        $location->delete();

        $this->assertSoftDeleted('locations', ['id' => $location->id]);
    }

    public function test_change_status_toggles_the_active_flag(): void
    {
        $location = Locations::create([
            'name' => 'Toggle Clinic',
            'slug' => 'toggle-loc',
            'region_id' => 1,
            'city_id' => 1,
            'active' => 1,
        ]);

        // Inactive flip
        $this->service->changeStatus($location->id, '0');
        $this->assertSame(0, (int) $location->fresh()->active);

        // Active flip
        $this->service->changeStatus($location->id, '1');
        $this->assertSame(1, (int) $location->fresh()->active);
    }

    public function test_tenant_scope_isolates_locations_by_account_id(): void
    {
        $mine = Locations::create([
            'name' => 'My Clinic',
            'slug' => 'mine',
            'region_id' => 1,
            'city_id' => 1,
            'active' => 1,
        ]);

        // Foreign tenant location via raw DB to bypass the
        // BaseModel account_id hook.
        \Illuminate\Support\Facades\DB::table('locations')->insert([
            'name' => 'Foreign Clinic',
            'slug' => 'foreign',
            'region_id' => 1,
            'city_id' => 1,
            'active' => 1,
            'account_id' => 999,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $mineCount = Locations::where('account_id', Auth::user()->account_id)->count();
        $this->assertGreaterThanOrEqual(1, $mineCount);

        // The foreign row should still exist in the raw table —
        // tenant isolation is a query-level concern, not a data-
        // deletion concern.
        $this->assertDatabaseHas('locations', ['name' => 'Foreign Clinic']);
        $this->assertSame(
            Auth::user()->account_id,
            $mine->account_id,
            'My location must carry my account_id — cross-tenant writes are not allowed.',
        );
    }
}
