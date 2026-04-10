<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Models\Resources;
use App\Services\Resource\ResourceService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Resources is the pool of bookable things that appointments attach
 * to (consult rooms, laser machines, staff seats). This test pins the
 * basic CRUD surface exposed by ResourceService — the more elaborate
 * location/machine-type filtering is deliberately left for the
 * scheduling feature tests.
 *
 * Invariants pinned:
 *   1. `store()` wraps the array in an internal Request and persists
 *      a row via Resources::createRecord — returns bool success.
 *   2. `update()` patches the row and refreshes it.
 *   3. `destroy()` soft-deletes and returns a status/message array.
 *   4. `changeStatus()` flips the active flag via active/inactive
 *      record paths.
 */
class ResourceCrudTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private ResourceService $service;

    private int $resourceTypeId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();

        $this->service = app(ResourceService::class);

        // resource_types is not in the shared fixture trait because
        // only this test touches it — seed a single "Room" type inline
        // so the FK resources.resource_type_id -> resource_types.id
        // can be satisfied.
        $this->resourceTypeId = (int) DB::table('resource_types')->insertGetId([
            'name' => 'Room',
            'slug' => 'room',
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Consult Room 1',
            'resource_type_id' => $this->resourceTypeId,
            'location_id' => $this->defaultLocation?->id ?? $this->seedDefaultLocation()->id,
            'active' => 1,
        ], $overrides);
    }

    public function test_store_persists_a_new_resource_row(): void
    {
        $success = $this->service->store($this->validPayload(), (int) Auth::user()->account_id);

        $this->assertTrue($success);
        $this->assertDatabaseHas('resources', ['name' => 'Consult Room 1']);
    }

    public function test_update_patches_the_row_with_new_fields(): void
    {
        $this->service->store($this->validPayload(), (int) Auth::user()->account_id);

        $existing = Resources::where('name', 'Consult Room 1')->firstOrFail();

        $success = $this->service->update(
            $existing->id,
            $this->validPayload(['name' => 'Consult Room 1 (Renamed)']),
            (int) Auth::user()->account_id,
        );

        $this->assertTrue($success);
        $this->assertSame('Consult Room 1 (Renamed)', $existing->fresh()->name);
    }

    public function test_destroy_soft_deletes_the_resource_row(): void
    {
        $this->service->store(
            $this->validPayload(['name' => 'Deletable Room']),
            (int) Auth::user()->account_id,
        );

        $existing = Resources::where('name', 'Deletable Room')->firstOrFail();

        $result = $this->service->destroy($existing->id);

        $this->assertTrue($result['status']);
        $this->assertSoftDeleted('resources', ['id' => $existing->id]);
    }

    public function test_change_status_to_zero_marks_the_resource_inactive(): void
    {
        $this->service->store(
            $this->validPayload(['name' => 'Toggle Room']),
            (int) Auth::user()->account_id,
        );
        $existing = Resources::where('name', 'Toggle Room')->firstOrFail();

        $this->service->changeStatus($existing->id, 0);

        $this->assertSame(0, (int) $existing->fresh()->active);
    }

    public function test_change_status_to_one_marks_the_resource_active(): void
    {
        $this->service->store(
            $this->validPayload(['name' => 'Reactivatable Room']),
            (int) Auth::user()->account_id,
        );
        $existing = Resources::where('name', 'Reactivatable Room')->firstOrFail();
        $existing->update(['active' => 0]);

        $this->service->changeStatus($existing->id, 1);

        $this->assertSame(1, (int) $existing->fresh()->active);
    }
}
