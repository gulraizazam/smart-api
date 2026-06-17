<?php

declare(strict_types=1);

namespace Tests\Feature\Leads;

use App\Models\Leads;
use App\Models\LeadStatuses;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Pins the contract for PUT /api/leads/storeleadstatus when the request
 * trips a system-managed status guard.
 *
 *   - Operator picks an `is_booked|is_arrived|is_converted` status as
 *     the target → backend returns 422 (NOT 500) with the message in
 *     both `message` and `errors.lead_status_parent_id[0]` so the SPA
 *     can wire `setError` if it needs to.
 *   - The SPA's Option A locked dropdown already keeps users out of
 *     this path under normal use; this test is the defense-in-depth
 *     guard against races, direct API hits, and future refactors that
 *     drop the locked-state check.
 *
 * Pre-fix the controller returned 500 — `api.ts` masks 5xx as a
 * generic "Something went wrong on our end. Please try again." which
 * hid the real reason.
 */
class UpdateLeadStatusGuardTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * UpdateLeadStatusRequest::authorize gates on the dotted `leads.update_status`
     * (the legacy<->dotted alias bridge was retired in Tier P P3). Grant the dotted
     * slug via a test role so the Spatie gate passes.
     */
    private function actingAsLeadStatusEditor(): User
    {
        $perm = $this->createPermission('leads.update_status');
        $role = $this->createRole('TestLeadStatusEditor');
        $role->givePermissionTo($perm);

        $actor = User::factory()->create(['account_id' => 1]);
        $this->assignRoleWithPivot($actor, $role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($actor);

        return $actor;
    }

    public function test_picking_a_booked_status_returns_422_with_structured_error(): void
    {
        $admin = $this->actingAsLeadStatusEditor();

        // Target the "Booked" status flag — auto-set by appointment
        // booking, not manually selectable.
        $bookedStatus = LeadStatuses::factory()->create([
            'name' => 'Booked',
            'is_booked' => 1,
            'account_id' => $admin->account_id,
        ]);
        $openStatus = LeadStatuses::factory()->create([
            'name' => 'Open',
            'account_id' => $admin->account_id,
        ]);
        $lead = Leads::factory()->create([
            'lead_status_id' => $openStatus->id,
            'account_id' => $admin->account_id,
        ]);

        $response = $this->putJson('/api/leads/storeleadstatus', [
            'id' => $lead->id,
            'lead_status_parent_id' => $bookedStatus->id,
        ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);

        // Message must be present (non-empty) and surfaced in the
        // field-keyed errors so RHF's setError can wire it inline.
        $body = $response->json();
        $this->assertNotEmpty($body['message']);
        $this->assertIsArray($body['errors']);
        $this->assertArrayHasKey('lead_status_parent_id', $body['errors']);
        $this->assertNotEmpty($body['errors']['lead_status_parent_id']);
    }

    public function test_changing_status_from_an_arrived_lead_returns_422(): void
    {
        // Outbound guard — the lead is at Arrived, operator tries to
        // move it to Open. Backend rejects; SPA gets 422 not 500.
        $admin = $this->actingAsLeadStatusEditor();

        $arrivedStatus = LeadStatuses::factory()->create([
            'name' => 'Arrived',
            'is_arrived' => 1,
            'account_id' => $admin->account_id,
        ]);
        $openStatus = LeadStatuses::factory()->create([
            'name' => 'Open',
            'account_id' => $admin->account_id,
        ]);
        $lead = Leads::factory()->create([
            'lead_status_id' => $arrivedStatus->id,
            'account_id' => $admin->account_id,
        ]);

        $response = $this->putJson('/api/leads/storeleadstatus', [
            'id' => $lead->id,
            'lead_status_parent_id' => $openStatus->id,
        ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
    }

    public function test_response_is_json_not_a_5xx_html_page(): void
    {
        // Pin Content-Type — the legacy 500 path could surface an HTML
        // debug page in dev. The SPA expects JSON for ApiError parsing.
        $admin = $this->actingAsLeadStatusEditor();

        $convertedStatus = LeadStatuses::factory()->create([
            'name' => 'Converted',
            'is_converted' => 1,
            'account_id' => $admin->account_id,
        ]);
        $openStatus = LeadStatuses::factory()->create([
            'name' => 'Open',
            'account_id' => $admin->account_id,
        ]);
        $lead = Leads::factory()->create([
            'lead_status_id' => null,
            'account_id' => $admin->account_id,
        ]);

        $response = $this->putJson('/api/leads/storeleadstatus', [
            'id' => $lead->id,
            'lead_status_parent_id' => $convertedStatus->id,
        ]);

        $this->assertStringContainsString(
            'application/json',
            (string) $response->headers->get('Content-Type'),
        );
        $this->assertSame(
            422,
            $response->status(),
            'Status guard violations must return 422 (not 200, not 500). The SPA masks 5xx as a generic toast and parses 422 errors via setError.',
        );
    }
}
