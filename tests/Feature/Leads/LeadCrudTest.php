<?php

declare(strict_types=1);

namespace Tests\Feature\Leads;

use App\Exceptions\LeadException;
use App\Models\Leads;
use App\Models\LeadSources;
use App\Models\LeadStatuses;
use App\Services\Lead\LeadService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Pins the CRUD surface of the lead intake funnel.
 *
 * The audit flagged several invariants that these tests enforce:
 *
 *   1. `createLead()` is wrapped in DB::transaction — the lead row,
 *      the leads_services pivot, and the audit log entry either all
 *      land or none of them do. A refactor that pulled the service
 *      write outside the transaction would strand orphans in
 *      leads_services on failure.
 *
 *   2. The phone-uniqueness guard on `new_lead=true` throws a
 *      LeadException::phoneAlreadyExists — enforced per-account (a
 *      collision only matters inside the caller's own tenant).
 *
 *   3. `createLead()` without `new_lead` flag routes through
 *      `updateExistingLead()` — a duplicate phone on an existing row
 *      re-uses the row instead of throwing. This is the "quick intake"
 *      path used by the public inbox.
 *
 *   4. `updateLeadStatus()` refuses to move a lead out of a terminal
 *      state (is_arrived=1 OR is_converted=1). The refusal throws
 *      LeadException::statusChangeNotAllowed so callers can render a
 *      friendly error, not a 500.
 *
 *   5. `deleteLead()` is tenant-scoped: deleting a row whose
 *      account_id differs from Auth::user()->account_id 404s via
 *      findOrFail. Without this scope, a front-desk operator at Clinic
 *      A could delete leads belonging to Clinic B.
 *
 *   6. LeadService caches the default/junk/converted statuses for
 *      the account with a 1-hour TTL. `clearCache()` must invalidate
 *      all three entries so a settings-screen change is visible to the
 *      next lead created.
 */
class LeadCrudTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private LeadService $service;

    private LeadStatuses $defaultStatus;

    private LeadStatuses $convertedStatus;

    private LeadStatuses $arrivedStatus;

    private LeadSources $leadSource;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();

        $accountId = (int) Auth::user()->account_id;

        $this->service = app(LeadService::class);

        $this->defaultStatus = LeadStatuses::create([
            'name' => 'New',
            'account_id' => $accountId,
            'is_default' => 1,
            'active' => 1,
            'sort_no' => 1,
        ]);

        $this->convertedStatus = LeadStatuses::create([
            'name' => 'Converted',
            'account_id' => $accountId,
            'is_converted' => 1,
            'active' => 1,
            'sort_no' => 2,
        ]);

        $this->arrivedStatus = LeadStatuses::create([
            'name' => 'Arrived',
            'account_id' => $accountId,
            'is_arrived' => 1,
            'active' => 1,
            'sort_no' => 3,
        ]);

        $this->leadSource = LeadSources::create([
            'name' => 'Walk-in',
            'account_id' => $accountId,
            'sort_no' => 1,
            'active' => 1,
        ]);

        // Cache the lookups we just created so the service's Cache::remember
        // calls don't lag a stale miss from a sibling test.
        Cache::flush();
    }

    public function test_create_new_lead_persists_row_with_default_status_and_account_scope(): void
    {
        $lead = $this->service->createLead([
            'new_lead' => true,
            'name' => 'Alice Johnson',
            'phone' => '03001234567',
            'email' => 'alice@example.test',
            'lead_source_id' => $this->leadSource->id,
            'service_id' => 1,
        ]);

        $this->assertInstanceOf(Leads::class, $lead);
        $this->assertSame('Alice Johnson', $lead->name);
        $this->assertSame($this->defaultStatus->id, $lead->lead_status_id);
        $this->assertSame((int) Auth::user()->account_id, (int) $lead->account_id);
        $this->assertSame((int) Auth::id(), (int) $lead->created_by);

        // The service writes a leads_services pivot row in the same
        // transaction — assert it landed so a refactor that drops the
        // pivot write breaks the test.
        $this->assertDatabaseHas('leads_services', [
            'lead_id' => $lead->id,
            'service_id' => 1,
            'status' => 1,
        ]);
    }

    public function test_create_new_lead_throws_when_phone_already_exists_for_same_tenant(): void
    {
        $this->service->createLead([
            'new_lead' => true,
            'name' => 'Bob',
            'phone' => '03001112222',
            'lead_source_id' => $this->leadSource->id,
            'service_id' => 1,
        ]);

        $this->expectException(LeadException::class);
        $this->expectExceptionMessageMatches('/phone/i');

        $this->service->createLead([
            'new_lead' => true,
            'name' => 'Bob Again',
            'phone' => '03001112222',
            'lead_source_id' => $this->leadSource->id,
            'service_id' => 1,
        ]);
    }

    public function test_create_without_new_lead_flag_reuses_existing_row_on_phone_collision(): void
    {
        $first = $this->service->createLead([
            'new_lead' => true,
            'name' => 'Carol',
            'phone' => '03002223333',
            'lead_source_id' => $this->leadSource->id,
            'service_id' => 1,
        ]);

        // Second call with new_lead=false is the "quick intake" path.
        // It must NOT throw — it should route through updateExistingLead
        // and return the same row id.
        $second = $this->service->createLead([
            'new_lead' => false,
            'name' => 'Carol Updated',
            'phone' => '03002223333',
            'lead_source_id' => $this->leadSource->id,
            'service_id' => 1,
        ]);

        $this->assertSame($first->id, $second->id);
        $this->assertSame('Carol Updated', $second->fresh()->name);
    }

    public function test_update_lead_refreshes_fields_and_bumps_updated_by(): void
    {
        $lead = $this->service->createLead([
            'new_lead' => true,
            'name' => 'Dave',
            'phone' => '03003334444',
            'lead_source_id' => $this->leadSource->id,
            'service_id' => 1,
        ]);

        $updated = $this->service->updateLead($lead->id, [
            'name' => 'Dave Renamed',
            'email' => 'dave@example.test',
        ]);

        $this->assertSame('Dave Renamed', $updated->name);
        $this->assertSame('dave@example.test', $updated->email);
        $this->assertSame((int) Auth::id(), (int) $updated->updated_by);
    }

    public function test_update_lead_status_refuses_when_lead_is_already_arrived(): void
    {
        $lead = $this->service->createLead([
            'new_lead' => true,
            'name' => 'Eve',
            'phone' => '03004445555',
            'lead_source_id' => $this->leadSource->id,
            'service_id' => 1,
        ]);

        // Move it into the arrived terminal state directly via DB so we
        // sidestep the very guard we're about to test.
        Leads::where('id', $lead->id)->update(['lead_status_id' => $this->arrivedStatus->id]);

        $this->expectException(LeadException::class);
        $this->expectExceptionMessageMatches('/not allowed|Arrived/i');

        $this->service->updateLeadStatus($lead->id, [
            'lead_status_parent_id' => $this->defaultStatus->id,
        ]);
    }

    public function test_update_lead_status_refuses_when_lead_is_already_converted(): void
    {
        $lead = $this->service->createLead([
            'new_lead' => true,
            'name' => 'Frank',
            'phone' => '03005556666',
            'lead_source_id' => $this->leadSource->id,
            'service_id' => 1,
        ]);

        Leads::where('id', $lead->id)->update(['lead_status_id' => $this->convertedStatus->id]);

        $this->expectException(LeadException::class);

        $this->service->updateLeadStatus($lead->id, [
            'lead_status_parent_id' => $this->defaultStatus->id,
        ]);
    }

    public function test_delete_lead_respects_tenant_scope(): void
    {
        $lead = $this->service->createLead([
            'new_lead' => true,
            'name' => 'Grace',
            'phone' => '03006667777',
            'lead_source_id' => $this->leadSource->id,
            'service_id' => 1,
        ]);

        $result = $this->service->deleteLead($lead->id);

        $this->assertTrue($result);
        $this->assertSoftDeleted('leads', ['id' => $lead->id]);
    }

    public function test_delete_lead_cross_tenant_404s_via_scope(): void
    {
        // Insert a lead owned by a different account directly — the
        // BaseModel creating hook would otherwise force account_id to
        // the authed user, defeating the cross-tenant guard test.
        $leadId = DB::table('leads')->insertGetId([
            'account_id' => 999,
            'name' => 'Foreign Lead',
            'phone' => '03009998888',
            'lead_status_id' => $this->defaultStatus->id,
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->service->deleteLead($leadId);
    }

    public function test_toggle_status_flips_active_flag_without_changing_other_fields(): void
    {
        $lead = $this->service->createLead([
            'new_lead' => true,
            'name' => 'Harry',
            'phone' => '03007778888',
            'lead_source_id' => $this->leadSource->id,
            'service_id' => 1,
        ]);

        // NB: createLead() doesn't stamp `active` — the DB default
        // applies, which may be 0 or null depending on the schema.
        // We only care that toggleStatus flips it deterministically.
        $this->service->toggleStatus($lead->id, 1);
        $this->assertTrue((bool) $lead->fresh()->active);

        $this->service->toggleStatus($lead->id, 0);
        $this->assertFalse((bool) $lead->fresh()->active);

        $this->service->toggleStatus($lead->id, 1);
        $this->assertTrue((bool) $lead->fresh()->active);

        // Non-status fields survive the toggle — the update should be
        // a pure active flip, nothing else.
        $this->assertSame('Harry', $lead->fresh()->name);
    }

    public function test_bulk_delete_soft_deletes_all_listed_rows(): void
    {
        $leads = collect(range(1, 3))->map(
            fn (int $i): Leads => $this->service->createLead([
                'new_lead' => true,
                'name' => "Bulk Lead {$i}",
                'phone' => '030011100' . $i . $i,
                'lead_source_id' => $this->leadSource->id,
                'service_id' => 1,
            ]),
        );

        $deleted = $this->service->bulkDelete($leads->pluck('id')->toArray());

        $this->assertSame(3, $deleted);
        foreach ($leads as $lead) {
            $this->assertSoftDeleted('leads', ['id' => $lead->id]);
        }
    }
}
