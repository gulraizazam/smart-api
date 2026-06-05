<?php

declare(strict_types=1);

namespace Tests\Feature\Memberships;

use App\Models\Appointments;
use App\Models\Membership;
use App\Models\Patients;
use App\Services\Membership\MembershipService;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Pins the memberships datatable "Status: Active" filter against the
 * stale-filter leak.
 *
 * The SPA owns its own filter state and sends the COMPLETE set on every
 * request — it never uses the legacy KTDatatable `filter` / `filter_cancel`
 * protocol. The service's per-user file cache (App\Helpers\Filters) is a
 * Blade-era convenience that back-fills any *unsent* filter from disk when a
 * request is not "authoritative" (applyFilter=false).
 *
 * That back-fill is the bug: a prior "Assignment: Unassigned"
 * (patient_id IS NULL) survived into a later "Status: Active"
 * (patient_id IS NOT NULL) request, producing a contradictory WHERE and an
 * empty list. The fix makes the SPA endpoint authoritative (applyFilter=true)
 * so unsent filters are forgotten, never leaked.
 *
 * Acts as Super-Admin (sees all centres). applyCentreAccess() then requires
 * the membership's patient to have an appointment at a visible centre, so the
 * fixture wires one — keeping the assertions about the status/assignment
 * filter logic, not centre scoping.
 */
class MembershipStatusFilterStaleCacheTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private MembershipService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
        $this->svc = app(MembershipService::class);

        // Factory default: assigned (patient_id set), active=1, end_date = +1y
        // → qualifies for the "active" bucket (assigned + valid). The patient
        // needs an appointment at a visible centre to clear applyCentreAccess.
        $patient = Patients::factory()->create();
        Appointments::factory()->create([
            'patient_id' => $patient->id,
            'location_id' => $this->defaultLocation->id,
        ]);
        Membership::factory()->create(['patient_id' => $patient->id]);
    }

    private function search(array $search, bool $applyFilter): array
    {
        return $this->svc->getDatatableData(['query' => ['search' => $search]], $applyFilter);
    }

    public function test_active_filter_returns_the_active_membership_in_clean_state(): void
    {
        $data = $this->search(['status' => 'active'], true);

        $this->assertSame(1, $data['total']);
    }

    public function test_legacy_non_authoritative_request_leaks_stale_assignment_filter(): void
    {
        // Reproduces the pre-fix bug. First request caches Assignment=Unassigned.
        $this->search(['assigned' => '0'], true);

        // A later Status=Active request that is NOT authoritative back-fills the
        // cached patient_id IS NULL, contradicting patient_id IS NOT NULL.
        $data = $this->search(['status' => 'active'], false);

        $this->assertSame(0, $data['total'], 'Stale assignment filter must contradict active and empty the list under the legacy path.');
    }

    public function test_authoritative_request_clears_stale_filter_and_shows_active(): void
    {
        // Same stale state…
        $this->search(['assigned' => '0'], true);

        // …but the SPA endpoint now treats every request as authoritative, so
        // the stale Assignment filter is forgotten and Active works.
        $data = $this->search(['status' => 'active'], true);

        $this->assertSame(1, $data['total']);
    }
}
