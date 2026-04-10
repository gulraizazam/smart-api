<?php

declare(strict_types=1);

namespace Tests\Feature\Memberships;

use App\Models\Membership;
use App\Models\MembershipType;
use App\Models\Patients;
use App\Services\Membership\MembershipRenewalService;
use App\Services\Membership\MembershipService;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Expiry is the read-side contract the clinic's "expiring memberships
 * today" widget depends on. Two queries matter:
 *
 *   1. `scopeExpired()` — rows whose end_date < today. These surface
 *      on the "cancel expired memberships" cleanup command and on the
 *      admin's expired-report page.
 *
 *   2. `scopeNotExpired()` / `scopeActive()` combined — the "who can
 *      redeem services today" query. This is what the discount
 *      engine hangs off, so incorrect filtering here leaks free
 *      services to expired-membership holders.
 *
 * Corner cases the audit was worried about:
 *   - A membership whose end_date is literally today — must be
 *     considered NOT expired. (The scope uses `<` vs `>=` rather than
 *     `<=`; the boundary is inclusive of today.)
 *   - An assigned membership with active=0 (manually deactivated);
 *     the active scope must filter it out even if its date range is
 *     still valid.
 *   - An unassigned code (patient_id = null) never shows up in the
 *     expiring-for-patient widget.
 */
class MembershipExpiryTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
    }

    public function test_expired_scope_returns_only_rows_whose_end_date_is_in_the_past(): void
    {
        $tier = MembershipType::factory()->create();

        $expired = Membership::factory()->create([
            'membership_type_id' => $tier->id,
            'patient_id' => Patients::factory()->create()->id,
            'start_date' => now()->subYears(2),
            'end_date' => now()->subMonth(),
            'active' => 1,
        ]);
        $valid = Membership::factory()->create([
            'membership_type_id' => $tier->id,
            'patient_id' => Patients::factory()->create()->id,
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
            'active' => 1,
        ]);

        $expiredRows = Membership::query()->expired()->get();

        $this->assertTrue($expiredRows->contains('id', $expired->id));
        $this->assertFalse($expiredRows->contains('id', $valid->id));
    }

    public function test_not_expired_scope_includes_a_membership_ending_today(): void
    {
        // The scope uses `end_date >= today` — today's end is still
        // a valid day of service. Pin the boundary so a refactor
        // tightening it to `>` doesn't quietly evict same-day
        // patients from their discount.
        $tier = MembershipType::factory()->create();
        $endsToday = Membership::factory()->create([
            'membership_type_id' => $tier->id,
            'patient_id' => Patients::factory()->create()->id,
            'end_date' => now()->toDateString(),
            'active' => 1,
        ]);

        $notExpired = Membership::query()->notExpired()->get();

        $this->assertTrue(
            $notExpired->contains('id', $endsToday->id),
            'A membership with end_date == today must be considered NOT expired.'
        );
    }

    public function test_active_scope_filters_out_manually_deactivated_memberships(): void
    {
        $tier = MembershipType::factory()->create();
        $live = Membership::factory()->create([
            'membership_type_id' => $tier->id,
            'patient_id' => Patients::factory()->create()->id,
            'active' => 1,
        ]);
        $inactive = Membership::factory()->create([
            'membership_type_id' => $tier->id,
            'patient_id' => Patients::factory()->create()->id,
            'active' => 0,
        ]);

        $activeRows = Membership::query()->active()->get();

        $this->assertTrue($activeRows->contains('id', $live->id));
        $this->assertFalse($activeRows->contains('id', $inactive->id));
    }

    public function test_get_patient_active_membership_returns_null_when_expired(): void
    {
        // Pin the read path used by the discount engine: a patient
        // whose membership has expired gets null back, so discounts
        // keyed on the active membership are simply not applied.
        $service = app(MembershipService::class);
        $patient = Patients::factory()->create();
        $tier = MembershipType::factory()->create();

        Membership::factory()->create([
            'membership_type_id' => $tier->id,
            'patient_id' => $patient->id,
            'start_date' => now()->subYears(2),
            'end_date' => now()->subMonth(),
            'active' => 1,
        ]);

        // Clear any cached lookup before and after (the service
        // memoizes via Cache::remember, 5 min TTL).
        \Illuminate\Support\Facades\Cache::flush();
        $this->assertNull($service->getPatientActiveMembership($patient->id));
    }

    public function test_get_patient_active_membership_returns_the_current_row_when_valid(): void
    {
        $service = app(MembershipService::class);
        $patient = Patients::factory()->create();
        $tier = MembershipType::factory()->create();

        $live = Membership::factory()->create([
            'membership_type_id' => $tier->id,
            'patient_id' => $patient->id,
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
            'active' => 1,
        ]);

        \Illuminate\Support\Facades\Cache::flush();
        $current = $service->getPatientActiveMembership($patient->id);

        $this->assertNotNull($current);
        $this->assertSame($live->id, $current->id);
    }

    public function test_get_expiring_memberships_returns_rows_inside_the_configured_window(): void
    {
        // The "notify patient their membership is expiring" job pulls
        // via MembershipService::getExpiringMemberships($days). Pin
        // that a 15-day horizon includes the 10-day row but excludes
        // the 60-day row.
        $service = app(MembershipService::class);
        $tier = MembershipType::factory()->create();

        $expiresIn10Days = Membership::factory()->create([
            'membership_type_id' => $tier->id,
            'patient_id' => Patients::factory()->create()->id,
            'end_date' => now()->addDays(10),
            'active' => 1,
        ]);
        $expiresIn60Days = Membership::factory()->create([
            'membership_type_id' => $tier->id,
            'patient_id' => Patients::factory()->create()->id,
            'end_date' => now()->addDays(60),
            'active' => 1,
        ]);

        $rows = $service->getExpiringMemberships(15);

        $this->assertTrue($rows->contains('id', $expiresIn10Days->id));
        $this->assertFalse($rows->contains('id', $expiresIn60Days->id));
    }

    public function test_get_expired_memberships_surfaces_only_past_dated_assigned_rows(): void
    {
        $service = app(MembershipService::class);
        $tier = MembershipType::factory()->create();

        $assignedExpired = Membership::factory()->create([
            'membership_type_id' => $tier->id,
            'patient_id' => Patients::factory()->create()->id,
            'end_date' => now()->subDays(5),
            'active' => 1,
        ]);
        $unassignedExpired = Membership::factory()->create([
            'membership_type_id' => $tier->id,
            'patient_id' => null,
            'end_date' => now()->subDays(5),
            'active' => 1,
        ]);

        $rows = $service->getExpiredMemberships();

        $this->assertTrue(
            $rows->contains('id', $assignedExpired->id),
            'An expired assigned membership must appear in the expired list.'
        );
        $this->assertFalse(
            $rows->contains('id', $unassignedExpired->id),
            'An expired unassigned code is a pool voucher, not an expired patient membership; '
            . 'it must NOT appear in the expired list.'
        );
    }

    public function test_renewal_service_lists_patients_with_expiring_memberships_in_window(): void
    {
        $service = app(MembershipRenewalService::class);
        $tier = MembershipType::factory()->create(['period' => 12]);

        Membership::factory()->create([
            'membership_type_id' => $tier->id,
            'patient_id' => Patients::factory()->create()->id,
            'end_date' => now()->addDays(7),
            'active' => 1,
        ]);
        Membership::factory()->create([
            'membership_type_id' => $tier->id,
            'patient_id' => Patients::factory()->create()->id,
            'end_date' => now()->addDays(90),
            'active' => 1,
        ]);

        $rows = $service->getPatientsWithExpiringMemberships(30);

        // One row falls inside the 30-day window.
        $this->assertGreaterThanOrEqual(1, $rows->count());
        foreach ($rows as $entry) {
            $this->assertArrayHasKey('membership', $entry);
            $this->assertArrayHasKey('renewal_options', $entry);
        }
    }
}
