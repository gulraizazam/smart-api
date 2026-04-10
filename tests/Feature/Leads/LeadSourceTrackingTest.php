<?php

declare(strict_types=1);

namespace Tests\Feature\Leads;

use App\Models\Leads;
use App\Models\LeadSources;
use App\Models\LeadStatuses;
use App\Services\Lead\LeadService;
use App\Services\Lead\LeadSourceService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Lead source tracking is the spine of the Marketing Report — every
 * new lead is stamped with `lead_source_id` and the report groups
 * conversion rates by source. If this linkage breaks, Marketing spend
 * attribution goes with it.
 *
 * Invariants pinned:
 *
 *   1. `LeadSourceService::create()` stamps account_id from the caller
 *      (not from the payload) and initialises `sort_no` to the row's
 *      own id — giving a stable initial ordering without a sort
 *      collision across tenants. A bulk-create in a loop must produce
 *      monotonic sort_nos equal to the row ids.
 *
 *   2. `LeadSourceService::update()` is tenant-scoped — a lead source
 *      owned by account 999 is invisible to an admin on account 1
 *      (`update()` returns null, it does NOT throw). This is the
 *      quiet-fail cross-tenant guard.
 *
 *   3. `LeadSourceService::delete()` refuses when child leads exist.
 *      Deleting a source referenced by active leads would orphan the
 *      `leads.lead_source_id` column — the service returns a
 *      structured `['status' => false, 'message' => ...]` instead of
 *      throwing, so the controller can render an inline error.
 *
 *   4. `LeadSourceService::toggleStatus()` flips the `active` flag
 *      without cascade-disabling the leads that already reference the
 *      source. An inactive source is only hidden from the NEW-lead
 *      picker, not retroactively from historical data.
 *
 *   5. A Lead's `lead_source_id` is the join column the marketing
 *      report uses; deleting the source (soft-delete) must NOT cascade
 *      to the leads row. Historical attribution survives the
 *      lifecycle of the source config.
 *
 *   6. `LeadSourceService::saveSortOrder()` re-writes `sort_no` based
 *      on the array position of each id — this is the drag-and-drop
 *      sort path on the settings screen.
 */
class LeadSourceTrackingTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private LeadSourceService $sourceService;

    private LeadService $leadService;

    private LeadStatuses $defaultStatus;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();

        $accountId = (int) Auth::user()->account_id;

        $this->sourceService = app(LeadSourceService::class);
        $this->leadService = app(LeadService::class);

        $this->defaultStatus = LeadStatuses::create([
            'name' => 'New',
            'account_id' => $accountId,
            'is_default' => 1,
            'active' => 1,
            'sort_no' => 1,
        ]);

        Cache::flush();
    }

    public function test_create_stamps_account_id_and_initialises_sort_no_to_row_id(): void
    {
        $accountId = (int) Auth::user()->account_id;

        $source = $this->sourceService->create([
            'name' => 'Facebook',
            'active' => 1,
        ], $accountId);

        $this->assertSame('Facebook', $source->name);
        $this->assertSame($accountId, (int) $source->account_id);
        $this->assertSame($source->id, (int) $source->sort_no);
    }

    public function test_bulk_create_produces_monotonic_sort_nos_matching_row_ids(): void
    {
        $accountId = (int) Auth::user()->account_id;

        $sources = collect(['Instagram', 'Google', 'Referral'])->map(
            fn (string $name): LeadSources => $this->sourceService->create(
                ['name' => $name, 'active' => 1],
                $accountId,
            ),
        );

        $sortValues = $sources->pluck('sort_no')->toArray();
        $idValues = $sources->pluck('id')->toArray();

        $this->assertSame($idValues, $sortValues);
        $this->assertSame(
            $sortValues,
            array_values(array_unique($sortValues)),
            'sort_no values must be unique — a collision would tie-break unpredictably in the report.',
        );
    }

    public function test_update_cross_tenant_source_returns_null_not_throws(): void
    {
        // Insert a source owned by account 999 using raw DB to
        // sidestep the BaseModel creating hook that otherwise forces
        // account_id to the authed user.
        $foreignId = DB::table('lead_sources')->insertGetId([
            'name' => 'Foreign Source',
            'account_id' => 999,
            'sort_no' => 1,
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = $this->sourceService->update(
            $foreignId,
            ['name' => 'Hijacked'],
            (int) Auth::user()->account_id,
        );

        $this->assertNull($result, 'Cross-tenant update must fail silently with null, not throw.');

        $this->assertDatabaseHas('lead_sources', [
            'id' => $foreignId,
            'name' => 'Foreign Source',
        ]);
    }

    public function test_delete_refuses_when_a_lead_references_the_source(): void
    {
        $accountId = (int) Auth::user()->account_id;

        $source = $this->sourceService->create([
            'name' => 'Phone',
            'active' => 1,
        ], $accountId);

        // Attach a lead to this source.
        $this->leadService->createLead([
            'new_lead' => true,
            'name' => 'Mike',
            'phone' => '03001010101',
            'lead_source_id' => $source->id,
            'service_id' => 1,
        ]);

        $result = $this->sourceService->delete($source->id);

        $this->assertFalse($result['status']);
        $this->assertStringContainsString('Child', $result['message']);
        $this->assertDatabaseHas('lead_sources', ['id' => $source->id, 'deleted_at' => null]);
    }

    public function test_delete_succeeds_when_no_leads_reference_the_source(): void
    {
        $accountId = (int) Auth::user()->account_id;

        $source = $this->sourceService->create([
            'name' => 'Brochure',
            'active' => 1,
        ], $accountId);

        $result = $this->sourceService->delete($source->id);

        $this->assertTrue($result['status']);
        $this->assertSoftDeleted('lead_sources', ['id' => $source->id]);
    }

    public function test_toggle_status_flips_active_without_cascading_to_historical_leads(): void
    {
        $accountId = (int) Auth::user()->account_id;

        $source = $this->sourceService->create([
            'name' => 'Radio',
            'active' => 1,
        ], $accountId);

        $lead = $this->leadService->createLead([
            'new_lead' => true,
            'name' => 'Nora',
            'phone' => '03002020202',
            'lead_source_id' => $source->id,
            'service_id' => 1,
        ]);

        $result = $this->sourceService->toggleStatus($source->id, 0);

        $this->assertTrue($result['status']);
        $this->assertSame(0, (int) $source->fresh()->active);

        // Historical lead still references the (now-inactive) source.
        // The marketing report depends on this — inactivating a source
        // is a UI-only hide, not a historical rewrite.
        $this->assertSame($source->id, (int) $lead->fresh()->lead_source_id);
    }

    public function test_leads_retain_source_link_after_source_is_soft_deleted(): void
    {
        $accountId = (int) Auth::user()->account_id;

        $source = $this->sourceService->create([
            'name' => 'Influencer',
            'active' => 1,
        ], $accountId);

        $lead = $this->leadService->createLead([
            'new_lead' => true,
            'name' => 'Olivia',
            'phone' => '03003030303',
            'lead_source_id' => $source->id,
            'service_id' => 1,
        ]);

        // Soft-delete the source directly (bypasses the
        // child-reference guard for the purpose of this test).
        LeadSources::where('id', $source->id)->delete();

        // The lead row still carries the original lead_source_id —
        // the soft-delete does not null it out. The report JOIN will
        // pick it up via `withTrashed()` in production.
        $this->assertSame($source->id, (int) $lead->fresh()->lead_source_id);

        $this->assertSoftDeleted('lead_sources', ['id' => $source->id]);
    }

    public function test_save_sort_order_rewrites_sort_no_by_array_position(): void
    {
        $accountId = (int) Auth::user()->account_id;

        $first = $this->sourceService->create(['name' => 'A', 'active' => 1], $accountId);
        $second = $this->sourceService->create(['name' => 'B', 'active' => 1], $accountId);
        $third = $this->sourceService->create(['name' => 'C', 'active' => 1], $accountId);

        // Drag-and-drop reorder: [C, A, B] — saveSortOrder writes
        // sort_no = 0 → C, 1 → A, 2 → B based on array position.
        $this->sourceService->saveSortOrder([$third->id, $first->id, $second->id]);

        $this->assertSame(0, (int) $third->fresh()->sort_no);
        $this->assertSame(1, (int) $first->fresh()->sort_no);
        $this->assertSame(2, (int) $second->fresh()->sort_no);
    }
}
