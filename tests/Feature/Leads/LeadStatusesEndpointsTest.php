<?php

declare(strict_types=1);

namespace Tests\Feature\Leads;

use App\Models\LeadStatuses;
use App\Services\Lead\LeadStatusService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Lead statuses controller + service invariants pinned alongside the
 * 2026-05-04 review:
 *
 *   1. saveSortOrder() applies the tenant scope on every UPDATE, so a
 *      reorder request submitted by account A with IDs from account B
 *      cannot rewrite B's sort_no. Pre-fix the service ran
 *      `LeadStatuses::where('id', $id)->update(...)` with no account
 *      guard — a confirmed cross-tenant write.
 *
 *   2. /api/lead_statuses/create returns the full UNIQUE_FLAGS set
 *      (is_default, is_booked, is_arrived, is_converted, is_junk) plus
 *      is_comment in the lead_status payload — pre-fix it omitted
 *      is_booked and is_comment, so the SPA's TS contract was a half-
 *      truth and could not seed those flags in the form.
 *
 *   3. The datatable response surfaces `create` and `sort` permissions
 *      so the SPA can gate the toolbar buttons (Add status / Reorder)
 *      instead of letting operators click through and eat a 401.
 */
class LeadStatusesEndpointsTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private LeadStatusService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();

        // Gate::before in AppServiceProvider auto-passes for the
        // 'Super-Admin' Spatie role, so we need that role on the actor
        // to drive the controller paths gated on lead_statuses_* perms.
        // The service-only test (LeadStatusServiceTest) doesn't need
        // this because it bypasses HTTP and calls the service directly.
        $superAdmin = $this->createRole('Super-Admin');
        $user = $this->actingAsAdmin();
        $this->assignRoleWithPivot($user, $superAdmin);

        $this->service = app(LeadStatusService::class);
    }

    public function test_save_sort_order_does_not_touch_rows_belonging_to_another_account(): void
    {
        $accountId = (int) Auth::user()->account_id;

        // Provision a second tenant: insert a fresh accounts row so the
        // lead_statuses.account_id FK can resolve to it.
        $otherAccountId = (int) DB::table('accounts')->insertGetId([
            'name' => 'Other Tenant',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $mine = LeadStatuses::create([
            'name' => 'Mine — Open',
            'parent_id' => null,
            'is_default' => 0, 'is_booked' => 0, 'is_arrived' => 0,
            'is_converted' => 0, 'is_junk' => 0, 'is_comment' => 0,
            'active' => 1,
            'sort_no' => 1,
        ]);

        // Bypass GuardsTenantBoundary's creating hook (which would
        // overwrite account_id to the actor's id) — this row exists
        // explicitly to model another tenant's data.
        $theirsId = DB::table('lead_statuses')->insertGetId([
            'name' => 'Theirs — Open',
            'account_id' => $otherAccountId,
            'parent_id' => null,
            'is_default' => 0, 'is_booked' => 0, 'is_arrived' => 0,
            'is_converted' => 0, 'is_junk' => 0, 'is_comment' => 0,
            'active' => 1,
            'sort_no' => 50,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Submit a sort that interleaves the other account's ID with
        // ours. The service must scope each UPDATE to the caller's
        // account, leaving the other account's row untouched.
        $this->service->saveSortOrder([$theirsId, $mine->id], $accountId);

        $theirsFresh = DB::table('lead_statuses')->where('id', $theirsId)->first();

        $this->assertSame(
            50,
            (int) $theirsFresh->sort_no,
            'Cross-account sort_no was rewritten — saveSortOrder must enforce account_id on every UPDATE.',
        );
        $this->assertSame(
            1,
            (int) $mine->fresh()->sort_no,
            'My row was at position 1 in the submitted order, sort_no should be 1.',
        );
    }

    public function test_save_sort_order_reorders_my_account_rows_correctly(): void
    {
        $accountId = (int) Auth::user()->account_id;

        $a = LeadStatuses::create([
            'name' => 'A', 'account_id' => $accountId, 'parent_id' => null,
            'is_default' => 0, 'is_booked' => 0, 'is_arrived' => 0,
            'is_converted' => 0, 'is_junk' => 0, 'is_comment' => 0,
            'active' => 1, 'sort_no' => 0,
        ]);
        $b = LeadStatuses::create([
            'name' => 'B', 'account_id' => $accountId, 'parent_id' => null,
            'is_default' => 0, 'is_booked' => 0, 'is_arrived' => 0,
            'is_converted' => 0, 'is_junk' => 0, 'is_comment' => 0,
            'active' => 1, 'sort_no' => 0,
        ]);
        $c = LeadStatuses::create([
            'name' => 'C', 'account_id' => $accountId, 'parent_id' => null,
            'is_default' => 0, 'is_booked' => 0, 'is_arrived' => 0,
            'is_converted' => 0, 'is_junk' => 0, 'is_comment' => 0,
            'active' => 1, 'sort_no' => 0,
        ]);

        $this->service->saveSortOrder([$c->id, $a->id, $b->id], $accountId);

        $this->assertSame(0, (int) $c->fresh()->sort_no);
        $this->assertSame(1, (int) $a->fresh()->sort_no);
        $this->assertSame(2, (int) $b->fresh()->sort_no);
    }

    public function test_create_endpoint_returns_full_unique_flag_payload(): void
    {
        $response = $this->getJson('/api/lead_statuses/create');

        $response->assertOk();
        $response->assertJson(fn ($json) => $json
            ->where('success', true)
            ->has('data.parentLeadStatuses')
            ->has('data.lead_status', fn ($ls) => $ls
                ->where('is_default', 0)
                ->where('is_booked', 0)
                ->where('is_arrived', 0)
                ->where('is_converted', 0)
                ->where('is_junk', 0)
                ->where('is_comment', 0)
            )
            ->etc()
        );
    }

    public function test_lead_statuses_dropdown_carries_lock_flags(): void
    {
        // The SPA leads table locks the "Update status" row action for
        // auto-derived statuses (booked/arrived/converted) by matching
        // these flags off this dropdown payload. Pre-fix the endpoint sent
        // only value/text, so the lock never engaged and a booked lead's
        // status was still editable from the table.
        $accountId = (int) Auth::user()->account_id;

        $booked = LeadStatuses::create([
            'name' => 'Booked', 'account_id' => $accountId, 'parent_id' => null,
            'is_default' => 0, 'is_booked' => 1, 'is_arrived' => 0,
            'is_converted' => 0, 'is_junk' => 0, 'is_comment' => 0,
            'active' => 1, 'sort_no' => 90,
        ]);
        $open = LeadStatuses::create([
            'name' => 'Open (dropdown test)', 'account_id' => $accountId, 'parent_id' => null,
            'is_default' => 0, 'is_booked' => 0, 'is_arrived' => 0,
            'is_converted' => 0, 'is_junk' => 0, 'is_comment' => 0,
            'active' => 1, 'sort_no' => 91,
        ]);

        $response = $this->getJson('/api/leads/lead_statuses');

        $response->assertOk();
        $items = collect($response->json());

        // Every item must carry all four lock/visibility flags.
        foreach ($items as $item) {
            $this->assertArrayHasKey('is_booked', $item);
            $this->assertArrayHasKey('is_arrived', $item);
            $this->assertArrayHasKey('is_converted', $item);
            $this->assertArrayHasKey('is_junk', $item);
        }

        $bookedItem = $items->firstWhere('value', $booked->id);
        $this->assertNotNull($bookedItem, 'Booked status missing from dropdown payload.');
        $this->assertTrue((bool) $bookedItem['is_booked'], 'Booked status must report is_booked=true so the SPA can lock it.');

        $openItem = $items->firstWhere('value', $open->id);
        $this->assertNotNull($openItem);
        $this->assertFalse((bool) $openItem['is_booked']);
        $this->assertFalse((bool) $openItem['is_arrived']);
        $this->assertFalse((bool) $openItem['is_converted']);
    }

    public function test_datatable_response_exposes_create_and_sort_permissions(): void
    {
        $response = $this->postJson('/api/lead_statuses/datatable', [
            'page' => 1,
            'perpage' => 25,
        ]);

        $response->assertOk();
        $response->assertJson(fn ($json) => $json
            ->has('permissions', fn ($p) => $p
                ->where('create', true)   // admin sees true; operator without the gate would see false
                ->where('edit', true)
                ->where('delete', true)
                ->where('active', true)
                ->where('inactive', true)
                ->where('sort', true)
            )
            ->etc()
        );
    }
}
