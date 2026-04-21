<?php

declare(strict_types=1);

namespace Tests\Feature\Leads;

use App\Http\Resources\Lead\LeadStatusFormResource;
use App\Models\LeadStatuses;
use App\Services\Lead\LeadStatusService;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Lead status service invariants.
 *
 *   1. `create()` coerces an empty parent_id to NULL, not 0. The
 *      `lead_statuses.parent_id` column carries a self-referencing FK
 *      (`fk_lead_statuses_parent_id`, added by migration
 *      2026_04_08_100038). Migration 2026_04_08_100034 backfilled all
 *      legacy 0-valued rows to NULL so the constraint could land; if
 *      `create()` re-introduced 0 on new rows, the insert would fail at
 *      the FK layer (SQLSTATE[23000]). Pinned so the coercion can't
 *      regress.
 *
 *   2. `update()` applies the same NULL coercion — a status edited to
 *      "no parent" must set parent_id to NULL, not 0.
 */
class LeadStatusServiceTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private LeadStatusService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();

        $this->service = app(LeadStatusService::class);
    }

    public function test_create_with_empty_parent_id_stores_null_not_zero(): void
    {
        $accountId = (int) Auth::user()->account_id;

        $status = $this->service->create([
            'name' => 'Open',
            'parent_id' => '',
            'is_default' => 1,
            'is_booked' => 0,
            'is_arrived' => 0,
            'is_converted' => 0,
            'is_junk' => 0,
            'is_comment' => 0,
            'active' => 1,
        ], $accountId);

        $this->assertNotNull($status->id);
        $this->assertNull(
            $status->fresh()->parent_id,
            'parent_id must be NULL when created without a parent — storing 0 violates fk_lead_statuses_parent_id.',
        );
    }

    public function test_create_with_zero_parent_id_stores_null_not_zero(): void
    {
        $accountId = (int) Auth::user()->account_id;

        $status = $this->service->create([
            'name' => 'Converted',
            'parent_id' => 0,
            'is_default' => 0,
            'is_booked' => 0,
            'is_arrived' => 0,
            'is_converted' => 1,
            'is_junk' => 0,
            'is_comment' => 0,
            'active' => 1,
        ], $accountId);

        $this->assertNull($status->fresh()->parent_id);
    }

    public function test_create_with_real_parent_id_persists_it(): void
    {
        $accountId = (int) Auth::user()->account_id;

        $parent = $this->service->create(
            ['name' => 'Open', 'is_default' => 0, 'is_booked' => 0, 'is_arrived' => 0, 'is_converted' => 0, 'is_junk' => 0, 'active' => 1],
            $accountId,
        );

        $child = $this->service->create([
            'name' => 'Open — Awaiting Callback',
            'parent_id' => $parent->id,
            'is_default' => 0,
            'is_booked' => 0,
            'is_arrived' => 0,
            'is_converted' => 0,
            'is_junk' => 0,
            'is_comment' => 0,
            'active' => 1,
        ], $accountId);

        $this->assertSame($parent->id, (int) $child->fresh()->parent_id);
    }

    public function test_update_to_no_parent_clears_to_null(): void
    {
        $accountId = (int) Auth::user()->account_id;

        $parent = $this->service->create(
            ['name' => 'Open', 'is_default' => 0, 'is_booked' => 0, 'is_arrived' => 0, 'is_converted' => 0, 'is_junk' => 0, 'active' => 1],
            $accountId,
        );
        $child = $this->service->create([
            'name' => 'Open — Follow Up',
            'parent_id' => $parent->id,
            'is_default' => 0, 'is_booked' => 0, 'is_arrived' => 0,
            'is_converted' => 0, 'is_junk' => 0, 'active' => 1,
        ], $accountId);

        $updated = $this->service->update($child->id, [
            'name' => $child->name,
            'parent_id' => '',
            'is_default' => 0, 'is_booked' => 0, 'is_arrived' => 0,
            'is_converted' => 0, 'is_junk' => 0, 'active' => 1,
        ], $accountId);

        $this->assertNotNull($updated);
        $this->assertNull($updated->fresh()->parent_id);
    }

    public function test_form_resource_returns_raw_integers_not_display_strings(): void
    {
        $accountId = (int) Auth::user()->account_id;

        $status = LeadStatuses::create([
            'name' => 'Test',
            'account_id' => $accountId,
            'parent_id' => null,
            'is_default' => 1,
            'is_junk' => 0,
            'is_comment' => 1,
            'active' => 1,
            'sort_no' => 1,
        ]);

        $resource = new LeadStatusFormResource($status);
        $payload = $resource->toArray(request());

        $this->assertSame(1, $payload['is_default']);
        $this->assertSame(0, $payload['is_junk']);
        $this->assertSame(1, $payload['is_comment']);
        $this->assertSame(0, $payload['parent_id']);
        $this->assertSame($status->name, $payload['name']);
    }
}
