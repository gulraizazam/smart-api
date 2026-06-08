<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Locations;
use App\Models\Services;
use App\Models\User;
use App\Services\Order\OrderService;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * The treatments/consultations list FILTER lookups must work for any role that
 * can view those lists:
 *
 *   1. The status filter (GET /api/appointment_statuses/dropdown) was gated on
 *      `appointment_statuses_manage` (a Settings-manage permission), so
 *      list-viewing roles (e.g. Feedback) got 403 and an empty status filter.
 *      It's now an ungated, account-scoped reference lookup.
 *   2. The doctor filter calls /api/get-doctors with NO location to get a
 *      "global" list; `getDoctorsWithFDM` matched location_id = 0 (nothing), so
 *      the filter was empty for everyone. A missing/zero location now falls
 *      back to the user's accessible centres.
 */
class ListFilterLookupsTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
    }

    private function actingWithPermissions(array $permissions): User
    {
        foreach ($permissions as $perm) {
            $this->createPermission($perm);
        }
        $role = $this->createRole('list-filter-test-'.uniqid());
        $role->givePermissionTo($permissions);

        $actor = User::factory()->create(['account_id' => 1]);
        $this->assignRoleWithPivot($actor, $role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($actor);

        return $actor;
    }

    public function test_status_dropdown_loads_without_appointment_statuses_manage(): void
    {
        // A list-viewing role that does NOT hold appointment_statuses_manage —
        // pre-fix this returned 403 and the status filter was empty.
        $this->actingWithPermissions(['treatments.list.view']);

        $this->getJson('/api/appointment_statuses/dropdown')->assertOk();
    }

    public function test_global_doctor_filter_resolves_via_the_users_centres(): void
    {
        // getDoctorsWithFDM looks up the 'FDM' role; ensure it exists (the
        // roles table has a non-null `commission` column).
        DB::table('roles')->updateOrInsert(
            ['name' => 'FDM', 'guard_name' => 'web'],
            ['commission' => 0, 'created_at' => now(), 'updated_at' => now()],
        );

        $location = Locations::factory()->create();
        $service = Services::factory()->create(['account_id' => 1]);

        // The acting (non-doctor) user is assigned to the centre, so
        // ACL::getUserCentres() returns it.
        $actor = User::factory()->create(['account_id' => 1]);
        DB::table('user_has_locations')->insert([
            'user_id' => $actor->id,
            'location_id' => $location->id,
            'region_id' => $location->region_id,
        ]);
        $this->actingAs($actor);

        // A doctor allocated to that centre.
        $doctor = User::factory()->doctor()->create(['account_id' => 1, 'active' => 1]);
        DB::table('doctor_has_locations')->insert([
            'user_id' => $doctor->id,
            'location_id' => $location->id,
            'service_id' => $service->id,
            'is_allocated' => 1,
            'end_node' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orders = app(OrderService::class);

        // Global (no location) — the broken case — now resolves via the
        // user's centres instead of returning empty.
        $this->assertArrayHasKey($doctor->id, $orders->getDoctorsWithFDM(0));

        // A real location still returns its doctors (unchanged behaviour).
        $this->assertArrayHasKey($doctor->id, $orders->getDoctorsWithFDM($location->id));
    }
}
