<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Pins User::toAuthPayload()['assigned_centre_ids'] — the authoritative,
 * ACL-scoped centre assignment the SPA uses to auto-select + lock every
 * centre/location filter when the user is bound to a single branch.
 *
 * Contract (mirrors ResourceScopeResolver):
 *   null  → company-wide (all centres)
 *   [id]  → exactly one centre  (SPA locks selectors to it)
 *   [...] → a regional subset
 */
class AuthPayloadCentresTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
    }

    private function attachCentre(int $userId, int $locationId): void
    {
        // ResourceScopeResolver only reads `location_id`; region_id has an FK
        // to `regions` we don't care about here, so bypass FK checks for the
        // fixture insert (region validity is irrelevant to the assertion).
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            DB::table('user_has_locations')->insert([
                'user_id' => $userId,
                'location_id' => $locationId,
                'region_id' => 0,
            ]);
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    public function test_single_assigned_centre_yields_one_id(): void
    {
        $user = User::factory()->create();
        $this->attachCentre($user->id, 7);

        $payload = $user->fresh()->toAuthPayload();

        $this->assertSame([7], $payload['assigned_centre_ids']);
    }

    public function test_multiple_assigned_centres_yield_the_subset(): void
    {
        $user = User::factory()->create();
        $this->attachCentre($user->id, 7);
        $this->attachCentre($user->id, 9);

        $payload = $user->fresh()->toAuthPayload();

        $this->assertEqualsCanonicalizing([7, 9], $payload['assigned_centre_ids']);
    }

    public function test_no_assigned_centres_yields_empty_list(): void
    {
        $user = User::factory()->create();

        $payload = $user->fresh()->toAuthPayload();

        // Not company-wide (that needs select_all / all-centres) and not a
        // single centre — the SPA must treat this as "no lock".
        $this->assertSame([], $payload['assigned_centre_ids']);
    }

    public function test_all_centres_pseudo_location_is_company_wide_null(): void
    {
        $allCentresId = (int) config('constants.all_centres_location_id');
        $user = User::factory()->create();
        $this->attachCentre($user->id, $allCentresId);

        $payload = $user->fresh()->toAuthPayload();

        $this->assertNull($payload['assigned_centre_ids']);
    }
}
