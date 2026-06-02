<?php

declare(strict_types=1);

namespace Tests\Feature\Leads;

use App\Models\Leads;
use App\Models\Locations;
use App\Models\User;
use App\Services\Lead\LeadService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Pins lead visibility to the user's assigned BRANCH (location_id), not their
 * city.
 *
 * Regression: the datatable scoped leads by ACL::getUserCities(). Because one
 * city can hold several branches (prod: city 1 → 3 centres, city 2 → 4), a
 * front-desk user assigned to a single branch saw every lead in that branch's
 * *city* — including a sibling branch's leads. The fix scopes by branch via
 * ResourceScopeResolver (the same authority the SPA reads as
 * `assigned_centre_ids`), with the null-location_id pool staying visible to
 * everyone and company-wide users unrestricted.
 *
 * @see \App\Models\Leads::scopeForBranches
 * @see \App\Services\Lead\LeadService::allowedBranchIds
 */
class LeadDatatableBranchScopeTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private LeadService $service;

    /** Two branches in the SAME city — the case city-scoping conflated. */
    private Locations $branchA;

    private Locations $branchB;

    private Leads $leadInBranchA;

    private Leads $leadInBranchB;

    private Leads $unassignedLead;

    protected function setUp(): void
    {
        parent::setUp();

        // Seeds account 1, region 1, city 1 — the FK chain LocationFactory
        // (which pins city_id/region_id/account_id to 1) depends on.
        $this->seedFinancialFixtures();

        $this->service = app(LeadService::class);

        $this->branchA = Locations::factory()->create(['account_id' => 1, 'city_id' => 1]);
        $this->branchB = Locations::factory()->create(['account_id' => 1, 'city_id' => 1]);

        // All three leads share city 1 — only location_id distinguishes them.
        $this->leadInBranchA = Leads::factory()->create([
            'account_id' => 1, 'city_id' => 1, 'location_id' => $this->branchA->id,
        ]);
        $this->leadInBranchB = Leads::factory()->create([
            'account_id' => 1, 'city_id' => 1, 'location_id' => $this->branchB->id,
        ]);
        $this->unassignedLead = Leads::factory()->create([
            'account_id' => 1, 'city_id' => 1, 'location_id' => null,
        ]);

        Cache::flush();
    }

    public function test_single_branch_user_sees_own_branch_and_unassigned_pool_but_not_a_sibling_branch(): void
    {
        $this->actingAsBranchUser([$this->branchA->id]);

        $result = $this->service->getDatatableData([]);
        $ids = $result['query']->get()->pluck('id')->all();

        $this->assertContains($this->leadInBranchA->id, $ids, 'User must see their own branch lead.');
        $this->assertContains($this->unassignedLead->id, $ids, 'Unassigned (null location_id) pool stays visible to all.');
        $this->assertNotContains(
            $this->leadInBranchB->id,
            $ids,
            'A sibling branch in the same city must NOT leak — this was the bug.'
        );
        $this->assertSame(2, $result['total']);
    }

    public function test_all_centres_user_is_company_wide_and_sees_every_branch(): void
    {
        // The "All Centres" pivot id ⇒ ResourceScopeResolver returns null ⇒
        // no branch restriction. Mirrors a head-office / company-wide user.
        $allCentresId = (int) Config::get('constants.all_centres_location_id');
        Locations::factory()->create(['id' => $allCentresId, 'account_id' => 1, 'city_id' => 1]);

        $this->actingAsBranchUser([$allCentresId]);

        $result = $this->service->getDatatableData([]);
        $ids = $result['query']->get()->pluck('id')->all();

        $this->assertContains($this->leadInBranchA->id, $ids);
        $this->assertContains($this->leadInBranchB->id, $ids);
        $this->assertContains($this->unassignedLead->id, $ids);
        $this->assertSame(3, $result['total']);
    }

    /**
     * @param  list<int>  $locationIds
     */
    private function actingAsBranchUser(array $locationIds): User
    {
        $user = User::factory()->create(['user_type_id' => 1, 'account_id' => 1]);

        foreach ($locationIds as $locationId) {
            DB::table('user_has_locations')->insert([
                'user_id' => $user->id,
                'location_id' => $locationId,
                'region_id' => 1,
            ]);
        }

        $this->actingAs($user);

        return $user;
    }
}
