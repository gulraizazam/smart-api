<?php

declare(strict_types=1);

namespace Tests\Feature\Leads;

use App\Models\Leads;
use App\Models\LeadsServices;
use App\Models\LeadStatuses;
use App\Models\Services;
use App\Services\Lead\BackfillLeadCategoryAction;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Pins the runtime behaviour of the backfill action that both the
 * booking flow (AppointmentsController) and conversion flow
 * (PlanService::markAppointmentAsConvertedOptimized) delegate to.
 *
 * Invariants the test protects:
 *
 *   1. Same-category fast path — if the lead's current active row
 *      already shares the new service's category, the action only
 *      refreshes metadata (consultancy_id, lead_status_id). History
 *      rows are not touched.
 *
 *   2. Different-category, existing history row — the action
 *      reactivates the demoted row for the new service instead of
 *      inserting a duplicate. Any currently-active rows are demoted
 *      to status=0 (history preserved, "soft" semantics).
 *
 *   3. Different-category, no prior row — the action creates a fresh
 *      pivot row at status=1 and demotes the current active row.
 *
 *   4. Category resolution walks `parent_id` — a child service's
 *      category is its parent; a root service is its own category.
 */
class BackfillLeadCategoryActionTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private BackfillLeadCategoryAction $action;

    private Leads $lead;

    private LeadStatuses $bookedStatus;

    /* Category A = parent with one child service. */
    private Services $categoryA;

    private Services $categoryAChild;

    /* Category B = parent with one child service. */
    private Services $categoryB;

    private Services $categoryBChild;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();

        $accountId = (int) Auth::user()->account_id;

        $this->action = app(BackfillLeadCategoryAction::class);

        $this->bookedStatus = LeadStatuses::create([
            'name' => 'Booked',
            'account_id' => $accountId,
            'is_booked' => 1,
            'active' => 1,
            'sort_no' => 1,
        ]);

        // Use the factory — it wires the tax_treatment_type_id FK for us.
        $this->categoryA = Services::factory()->create([
            'name' => 'Laser',
            'parent_id' => null,
        ]);
        $this->categoryAChild = Services::factory()->create([
            'name' => 'Laser – Hair Removal',
            'parent_id' => $this->categoryA->id,
        ]);

        $this->categoryB = Services::factory()->create([
            'name' => 'Facial',
            'parent_id' => null,
        ]);
        $this->categoryBChild = Services::factory()->create([
            'name' => 'Facial – Hydrafacial',
            'parent_id' => $this->categoryB->id,
        ]);

        $this->lead = Leads::create([
            'name' => 'Zara K.',
            'phone' => '03009998887',
            'account_id' => $accountId,
            'lead_status_id' => $this->bookedStatus->id,
            'created_by' => Auth::id(),
        ]);
    }

    public function test_same_category_only_refreshes_metadata_and_preserves_history_rows(): void
    {
        // Lead currently active on a child of category A.
        $activeRow = LeadsServices::create([
            'lead_id' => $this->lead->id,
            'service_id' => $this->categoryAChild->id,
            'status' => 1,
            'lead_status_id' => null,
        ]);
        // Plus a demoted row for category B — must not be touched.
        $demotedRow = LeadsServices::create([
            'lead_id' => $this->lead->id,
            'service_id' => $this->categoryBChild->id,
            'status' => 0,
            'lead_status_id' => null,
        ]);

        // Book against the category-A parent — same category as the child.
        $this->action->execute(
            lead: $this->lead,
            service: $this->categoryA,
            consultancyId: null,
            leadStatusId: $this->bookedStatus->id,
        );

        $activeRow->refresh();
        $demotedRow->refresh();

        // Active row kept, metadata refreshed, service_id NOT overwritten
        // (fast-path shouldn't rewrite the lead's specific service choice).
        $this->assertSame(1, (int) $activeRow->status);
        $this->assertSame($this->categoryAChild->id, (int) $activeRow->service_id);
        $this->assertSame($this->bookedStatus->id, (int) $activeRow->lead_status_id);

        // History row untouched.
        $this->assertSame(0, (int) $demotedRow->status);

        // Exactly two pivot rows — no new row was created.
        $this->assertSame(2, LeadsServices::where('lead_id', $this->lead->id)->count());
    }

    public function test_different_category_reactivates_existing_history_row_and_demotes_current(): void
    {
        // Lead currently active on category A.
        $activeRow = LeadsServices::create([
            'lead_id' => $this->lead->id,
            'service_id' => $this->categoryA->id,
            'status' => 1,
            'lead_status_id' => null,
        ]);
        // A prior inquiry for category B is sitting demoted.
        $bHistoryRow = LeadsServices::create([
            'lead_id' => $this->lead->id,
            'service_id' => $this->categoryB->id,
            'status' => 0,
            'lead_status_id' => null,
        ]);

        $this->action->execute(
            lead: $this->lead,
            service: $this->categoryB,
            consultancyId: 77,
            leadStatusId: $this->bookedStatus->id,
        );

        $activeRow->refresh();
        $bHistoryRow->refresh();

        // Old active demoted (history preserved, "soft" semantics).
        $this->assertSame(0, (int) $activeRow->status);
        // Existing B row reactivated, not duplicated.
        $this->assertSame(1, (int) $bHistoryRow->status);
        $this->assertSame($this->bookedStatus->id, (int) $bHistoryRow->lead_status_id);

        $this->assertSame(2, LeadsServices::where('lead_id', $this->lead->id)->count());
        $this->assertSame(1, LeadsServices::where('lead_id', $this->lead->id)->where('status', 1)->count());
    }

    public function test_different_category_without_prior_row_creates_new_active_row(): void
    {
        $activeRow = LeadsServices::create([
            'lead_id' => $this->lead->id,
            'service_id' => $this->categoryA->id,
            'status' => 1,
            'lead_status_id' => null,
        ]);

        $this->action->execute(
            lead: $this->lead,
            service: $this->categoryBChild,
            consultancyId: 99,
            leadStatusId: $this->bookedStatus->id,
        );

        $activeRow->refresh();
        $this->assertSame(0, (int) $activeRow->status);

        $newRow = LeadsServices::where('lead_id', $this->lead->id)
            ->where('service_id', $this->categoryBChild->id)
            ->first();

        $this->assertNotNull($newRow);
        $this->assertSame(1, (int) $newRow->status);
        $this->assertSame($this->bookedStatus->id, (int) $newRow->lead_status_id);

        $this->assertSame(1, LeadsServices::where('lead_id', $this->lead->id)->where('status', 1)->count());
    }

    public function test_no_active_row_yet_creates_new_row_and_touches_nothing_else(): void
    {
        // Only a demoted row exists — lead never had an active category.
        $demoted = LeadsServices::create([
            'lead_id' => $this->lead->id,
            'service_id' => $this->categoryA->id,
            'status' => 0,
            'lead_status_id' => null,
        ]);

        $this->action->execute(
            lead: $this->lead,
            service: $this->categoryBChild,
            consultancyId: 123,
            leadStatusId: null,
        );

        $demoted->refresh();
        $this->assertSame(0, (int) $demoted->status, 'Non-active history row must stay demoted.');

        $newRow = LeadsServices::where('lead_id', $this->lead->id)
            ->where('service_id', $this->categoryBChild->id)
            ->first();

        $this->assertNotNull($newRow);
        $this->assertSame(1, (int) $newRow->status);
    }

    public function test_child_service_resolves_category_via_parent_id(): void
    {
        // Active row on category-A child. Book against category-B child:
        // different parent → different category → demote + reactivate B's
        // child-level history row.
        LeadsServices::create([
            'lead_id' => $this->lead->id,
            'service_id' => $this->categoryAChild->id,
            'status' => 1,
            'lead_status_id' => null,
        ]);

        $this->action->execute(
            lead: $this->lead,
            service: $this->categoryBChild,
            consultancyId: 1,
            leadStatusId: null,
        );

        $this->assertSame(
            1,
            LeadsServices::where('lead_id', $this->lead->id)
                ->where('service_id', $this->categoryBChild->id)
                ->where('status', 1)
                ->count(),
        );
        $this->assertSame(
            0,
            LeadsServices::where('lead_id', $this->lead->id)
                ->where('service_id', $this->categoryAChild->id)
                ->value('status'),
        );
    }
}
