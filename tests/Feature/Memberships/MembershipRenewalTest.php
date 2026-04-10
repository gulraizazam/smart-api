<?php

declare(strict_types=1);

namespace Tests\Feature\Memberships;

use App\Exceptions\MembershipException;
use App\Models\Membership;
use App\Models\MembershipType;
use App\Models\Patients;
use App\Services\Membership\MembershipRenewalService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * MembershipRenewalService is the read-and-write path that binds a
 * fresh code to an existing patient when their current membership
 * is expiring. The audit cared about four invariants:
 *
 *   1. Renewal type resolution: a child MembershipType row (parent_id
 *      pointing at the current tier) is preferred; if none exists,
 *      the service falls back to the same tier. A future cleanup that
 *      deletes children must not silently break the flow.
 *
 *   2. Renewal code binding: the fresh code row must flip from
 *      unassigned to assigned, its date range stamped from the
 *      options OR derived from the old membership's end_date + 1 day.
 *
 *   3. Ledger row: every renewal writes exactly one row into the
 *      `membership_renewals` audit table. Without this, the clinic's
 *      "renewal count" metric silently under-reports. (The migration
 *      for this table was added alongside this test — the service
 *      had been writing to a non-existent table for months.)
 *
 *   4. Eligibility window: getRenewalOptions reports the current
 *      membership as "renewal available" only if it's expired or
 *      within 30 days of expiry. Pin both sides of the window.
 */
class MembershipRenewalTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private MembershipRenewalService $service;

    private Patients $patient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();

        $this->service = app(MembershipRenewalService::class);
        $this->patient = Patients::factory()->create();
    }

    public function test_renew_membership_binds_the_new_code_and_dates_are_stamped(): void
    {
        $parent = MembershipType::factory()->create(['period' => 12]);

        // Existing membership, expiring tomorrow.
        $existing = Membership::factory()->create([
            'membership_type_id' => $parent->id,
            'patient_id' => $this->patient->id,
            'start_date' => now()->subMonths(11),
            'end_date' => now()->addDay(),
            'active' => 1,
        ]);

        // An unassigned renewal code the front-desk will bind.
        $fresh = Membership::factory()->create([
            'membership_type_id' => $parent->id,
            'patient_id' => null,
            'start_date' => null,
            'end_date' => null,
        ]);

        $result = $this->service->renewMembership($this->patient->id, $fresh->code);

        $this->assertSame($this->patient->id, $result->patient_id);
        $this->assertNotNull($result->start_date);
        $this->assertNotNull($result->end_date);
        $this->assertNotNull($result->assigned_at);

        // start = existing.end_date + 1 day (the default in the service).
        $expectedStart = Carbon::parse($existing->end_date)->addDay()->format('Y-m-d');
        $this->assertSame($expectedStart, Carbon::parse($result->start_date)->format('Y-m-d'));
    }

    public function test_renew_membership_writes_a_row_to_membership_renewals(): void
    {
        // Pin the audit ledger. Without this row, the clinic's
        // "how many renewals this quarter?" report under-counts.
        // This test was the trigger for adding the migration that
        // creates the membership_renewals table at all.
        $parent = MembershipType::factory()->create();
        $existing = Membership::factory()->create([
            'membership_type_id' => $parent->id,
            'patient_id' => $this->patient->id,
            'end_date' => now()->addDays(5),
        ]);
        $fresh = Membership::factory()->create([
            'membership_type_id' => $parent->id,
            'patient_id' => null,
        ]);

        $before = DB::table('membership_renewals')->count();

        $renewed = $this->service->renewMembership($this->patient->id, $fresh->code);

        $after = DB::table('membership_renewals')->count();
        $this->assertSame($before + 1, $after, 'Renewal must append exactly one ledger row.');

        $row = DB::table('membership_renewals')
            ->where('renewed_membership_id', $renewed->id)
            ->first();

        $this->assertNotNull($row);
        $this->assertSame($existing->id, (int) $row->original_membership_id);
    }

    public function test_renew_membership_throws_when_the_patient_has_no_existing_membership(): void
    {
        $parent = MembershipType::factory()->create();
        $fresh = Membership::factory()->create([
            'membership_type_id' => $parent->id,
            'patient_id' => null,
        ]);

        $this->expectException(MembershipException::class);
        $this->expectExceptionMessageMatches('/No existing membership/i');

        $this->service->renewMembership($this->patient->id, $fresh->code);
    }

    public function test_renew_membership_throws_when_the_renewal_code_is_already_taken(): void
    {
        $parent = MembershipType::factory()->create();
        Membership::factory()->create([
            'membership_type_id' => $parent->id,
            'patient_id' => $this->patient->id,
            'end_date' => now()->addDay(),
        ]);

        // "Fresh" code is already held by another patient.
        $other = Patients::factory()->create();
        $taken = Membership::factory()->create([
            'membership_type_id' => $parent->id,
            'patient_id' => $other->id,
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonths(11),
        ]);

        $this->expectException(MembershipException::class);
        $this->expectExceptionMessageMatches('/already assigned/i');

        $this->service->renewMembership($this->patient->id, $taken->code);
    }

    public function test_get_renewal_options_marks_available_when_expiring_within_thirty_days(): void
    {
        $parent = MembershipType::factory()->create(['period' => 12]);
        Membership::factory()->create([
            'membership_type_id' => $parent->id,
            'patient_id' => $this->patient->id,
            'start_date' => now()->subMonths(11),
            'end_date' => now()->addDays(15),
            'active' => 1,
        ]);

        $options = $this->service->getRenewalOptions($this->patient->id);

        $this->assertTrue($options['has_membership']);
        $this->assertTrue(
            $options['renewal_available'],
            'A membership expiring in 15 days must be flagged as renewal-available.',
        );
    }

    public function test_get_renewal_options_marks_unavailable_when_far_from_expiry(): void
    {
        $parent = MembershipType::factory()->create(['period' => 12]);
        Membership::factory()->create([
            'membership_type_id' => $parent->id,
            'patient_id' => $this->patient->id,
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonths(10),
            'active' => 1,
        ]);

        $options = $this->service->getRenewalOptions($this->patient->id);

        $this->assertTrue($options['has_membership']);
        $this->assertFalse(
            $options['renewal_available'],
            'A membership expiring in 10 months must NOT be flagged as renewal-available.',
        );
    }

    public function test_get_renewal_options_returns_no_membership_when_patient_has_none(): void
    {
        $options = $this->service->getRenewalOptions($this->patient->id);

        $this->assertFalse($options['has_membership']);
        $this->assertFalse($options['renewal_available']);
    }

    public function test_is_eligible_for_renewal_follows_the_thirty_day_window(): void
    {
        $parent = MembershipType::factory()->create();
        $within = Membership::factory()->create([
            'membership_type_id' => $parent->id,
            'patient_id' => $this->patient->id,
            'end_date' => now()->addDays(10),
        ]);
        $outside = Membership::factory()->create([
            'membership_type_id' => $parent->id,
            'patient_id' => Patients::factory()->create()->id,
            'end_date' => now()->addMonths(6),
        ]);

        $this->assertTrue($this->service->isEligibleForRenewal($within->id));
        $this->assertFalse($this->service->isEligibleForRenewal($outside->id));
    }
}
