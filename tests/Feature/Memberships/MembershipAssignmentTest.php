<?php

declare(strict_types=1);

namespace Tests\Feature\Memberships;

use App\Exceptions\MembershipException;
use App\Models\Membership;
use App\Models\MembershipType;
use App\Models\Patients;
use App\Services\Membership\MembershipAssignmentService;
use App\Services\Membership\MembershipService;
use Carbon\Carbon;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * MembershipAssignmentService is the write-side of the membership
 * catalogue. The audit's concerns here:
 *
 *   1. Unassigned membership codes sit in the table as "vouchers" — a
 *      row with patient_id=NULL that reserves a code like GOLD-001.
 *      assignMembership() binds the code to a patient, stamps the
 *      date range, and MUST NOT re-bind an already-assigned code.
 *
 *   2. The per-patient overlap check is the audit's safety net against
 *      the "two active memberships of the same tier" double-discount
 *      loophole. Pin that overlapping date ranges are refused.
 *
 *   3. Assigning an inactive code or an inactive-type code must fail.
 *
 *   4. Cancelling an assignment MUST clear patient_id/start_date/
 *      end_date/assigned_at (so the code is released back to the pool
 *      and can be re-assigned to another patient without DB churn).
 */
class MembershipAssignmentTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private MembershipAssignmentService $service;

    private Patients $patient;

    private MembershipType $tier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();

        $this->service = app(MembershipAssignmentService::class);
        $this->patient = Patients::factory()->create();
        $this->tier = MembershipType::factory()->create([
            'period' => 12,
            'amount' => 25000,
            'active' => 1,
        ]);
    }

    public function test_assign_membership_binds_code_to_patient_and_stamps_date_range(): void
    {
        // Create an unassigned code (patient_id=null is the "voucher"
        // shape the clinic uses before handing a code to a patient).
        $membership = Membership::factory()->create([
            'membership_type_id' => $this->tier->id,
            'patient_id' => null,
            'start_date' => null,
            'end_date' => null,
            'assigned_at' => null,
            'active' => 1,
        ]);

        $result = $this->service->assignMembership($membership->code, $this->patient->id);

        $this->assertSame($this->patient->id, $result->patient_id);
        $this->assertNotNull($result->start_date);
        $this->assertNotNull($result->end_date);
        $this->assertNotNull($result->assigned_at);

        // Period=12 months → end should be ~12 months from start.
        $start = Carbon::parse($result->start_date);
        $end = Carbon::parse($result->end_date);
        $this->assertSame(12, (int) $start->diffInMonths($end));
    }

    public function test_assign_membership_refuses_an_already_assigned_code(): void
    {
        // An assigned code is "taken" — re-assignment is a bug shape
        // (would silently steal the code from patient A and give it
        // to patient B, leaving patient A's row history intact but
        // their code-level access transferred). Pin that the service
        // throws rather than overwriting.
        $other = Patients::factory()->create();
        $membership = Membership::factory()->create([
            'membership_type_id' => $this->tier->id,
            'patient_id' => $other->id,
            'start_date' => now()->startOfDay(),
            'end_date' => now()->startOfDay()->addYear(),
            'assigned_at' => now(),
            'active' => 1,
        ]);

        $this->expectException(MembershipException::class);
        $this->expectExceptionMessageMatches('/already assigned/i');

        $this->service->assignMembership($membership->code, $this->patient->id);
    }

    public function test_assign_membership_refuses_an_inactive_code(): void
    {
        $membership = Membership::factory()->create([
            'membership_type_id' => $this->tier->id,
            'patient_id' => null,
            'active' => 0,
        ]);

        $this->expectException(MembershipException::class);
        $this->expectExceptionMessageMatches('/inactive/i');

        $this->service->assignMembership($membership->code, $this->patient->id);
    }

    public function test_assign_membership_refuses_when_an_overlapping_membership_exists(): void
    {
        // The safety-net: a patient cannot hold two memberships of the
        // same tier that overlap in time. MembershipService
        // ::hasOverlappingMembership walks the active-membership set
        // for the patient and blocks the assign if the new date range
        // intersects any existing row.
        Membership::factory()->create([
            'membership_type_id' => $this->tier->id,
            'patient_id' => $this->patient->id,
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
            'active' => 1,
        ]);

        // A fresh code the patient tries to redeem — its default date
        // range (today to today+12mo) overlaps the existing active
        // membership, so the overlap check should refuse.
        $fresh = Membership::factory()->create([
            'membership_type_id' => $this->tier->id,
            'patient_id' => null,
        ]);

        $this->expectException(MembershipException::class);
        $this->expectExceptionMessageMatches('/active membership during this period/i');

        $this->service->assignMembership($fresh->code, $this->patient->id);
    }

    public function test_assign_membership_accepts_a_custom_start_and_end_date(): void
    {
        // The front-desk sometimes back-dates or forward-dates a
        // membership (e.g. a patient pays today but the tier's year
        // should begin on the 1st of next month). Pin that the
        // options array overrides the default.
        $membership = Membership::factory()->create([
            'membership_type_id' => $this->tier->id,
            'patient_id' => null,
        ]);

        $result = $this->service->assignMembership(
            $membership->code,
            $this->patient->id,
            [
                'start_date' => '2026-07-01',
                'end_date' => '2027-06-30',
            ],
        );

        $this->assertSame('2026-07-01', Carbon::parse($result->start_date)->format('Y-m-d'));
        $this->assertSame('2027-06-30', Carbon::parse($result->end_date)->format('Y-m-d'));
    }

    public function test_cancel_membership_releases_the_code_back_to_the_pool(): void
    {
        // After cancellation, the code row should still exist (the
        // clinic keeps its catalogue) but patient_id/start/end/
        // assigned_at are nulled so the code can be re-assigned.
        $membership = Membership::factory()->create([
            'membership_type_id' => $this->tier->id,
            'patient_id' => $this->patient->id,
            'start_date' => now()->subMonths(2),
            'end_date' => now()->addMonths(10),
            'assigned_at' => now()->subMonths(2),
            'active' => 1,
        ]);

        $result = $this->service->cancelMembership($this->patient->id);

        $this->assertTrue($result['success']);

        $membership->refresh();
        $this->assertNull($membership->patient_id);
        $this->assertNull($membership->start_date);
        $this->assertNull($membership->end_date);
        $this->assertNull($membership->assigned_at);
        // The row itself still exists — cancellation doesn't soft-delete.
        $this->assertNotNull(Membership::query()->find($membership->id));
    }

    public function test_cancel_membership_throws_when_the_patient_has_none(): void
    {
        $this->expectException(MembershipException::class);
        $this->expectExceptionMessageMatches('/No membership found/i');

        $this->service->cancelMembership($this->patient->id);
    }

    public function test_has_overlapping_membership_detects_exact_date_overlap(): void
    {
        $membershipService = app(MembershipService::class);

        Membership::factory()->create([
            'membership_type_id' => $this->tier->id,
            'patient_id' => $this->patient->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'active' => 1,
        ]);

        // Fully-contained range → overlap.
        $this->assertTrue($membershipService->hasOverlappingMembership(
            $this->patient->id,
            '2026-03-01',
            '2026-06-01',
        ));

        // Left-overlap (new starts before, ends during).
        $this->assertTrue($membershipService->hasOverlappingMembership(
            $this->patient->id,
            '2025-10-01',
            '2026-02-15',
        ));

        // Non-overlapping future range → no overlap.
        $this->assertFalse($membershipService->hasOverlappingMembership(
            $this->patient->id,
            '2027-01-01',
            '2027-06-30',
        ));
    }
}
