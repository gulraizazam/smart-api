<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Locations;
use App\Models\Services;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * POST /api/schedule/get-resources backs the repeating-shifts editor's
 * "also apply to" multiselect — the standalone doctor list for a location.
 * The route was missing, so the SPA's call 404'd and the dropdown was empty.
 * Pins: it returns the location's allocated active doctors, an empty location
 * yields an empty list (NOT an error — single-doctor locations are normal),
 * and a missing location_id is a 400.
 */
class ScheduleGetResourcesTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private const RESOURCE_TYPE_DOCTOR = 2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
    }

    /** Allocate a doctor to a location and give them a matching Resources row. */
    private function seedDoctorResource(int $locationId, string $name): int
    {
        DB::table('resource_types')->updateOrInsert(
            ['id' => self::RESOURCE_TYPE_DOCTOR],
            ['name' => 'Doctor', 'slug' => 'doctor', 'active' => 1, 'created_at' => now(), 'updated_at' => now()],
        );

        $doctor = User::factory()->doctor()->create(['account_id' => 1, 'active' => 1]);
        $service = Services::factory()->create(['account_id' => 1]);

        DB::table('doctor_has_locations')->insert([
            'user_id' => $doctor->id,
            'location_id' => $locationId,
            'service_id' => $service->id,
            'is_allocated' => 1,
            'end_node' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('resources')->insert([
            'account_id' => 1,
            'name' => $name,
            'active' => 1,
            'resource_type_id' => self::RESOURCE_TYPE_DOCTOR,
            'external_id' => $doctor->id,
            'location_id' => $locationId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $doctor->id;
    }

    public function test_returns_allocated_active_doctors_for_location(): void
    {
        $location = Locations::factory()->create();
        $aliceId = $this->seedDoctorResource($location->id, 'Dr Alice');
        $bobId = $this->seedDoctorResource($location->id, 'Dr Bob');

        $response = $this->postJson('/api/schedule/get-resources', [
            'location_id' => $location->id,
            'resource_type_id' => 2,
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['success', 'data' => ['resources' => [['id', 'name', 'external_id']]]]);

        $externalIds = array_column($response->json('data.resources'), 'external_id');
        $this->assertContains($aliceId, $externalIds);
        $this->assertContains($bobId, $externalIds);
        $this->assertCount(2, $response->json('data.resources'));
    }

    public function test_returns_empty_list_for_a_location_with_no_doctors(): void
    {
        $location = Locations::factory()->create();

        $response = $this->postJson('/api/schedule/get-resources', [
            'location_id' => $location->id,
            'resource_type_id' => 2,
        ]);

        // Empty is a valid result, not an error — a single-doctor location has
        // no OTHER doctors to copy a repeating pattern to.
        $response->assertOk();
        $this->assertSame([], $response->json('data.resources'));
    }

    public function test_missing_location_id_returns_400(): void
    {
        $response = $this->postJson('/api/schedule/get-resources', ['resource_type_id' => 2]);

        $response->assertStatus(400);
    }
}
