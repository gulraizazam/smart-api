<?php

declare(strict_types=1);

namespace Tests\Feature\Plan;

use App\Console\Commands\UpdateExpiredMemberships;
use App\Models\Membership;
use App\Services\Membership\MembershipService;
use App\Services\PatientManagement\PatientService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Referral memberships are tied to a parent Gold Membership via the
 * `memberships.parent_membership_code` column. The whole point of the
 * referral row is that it inherits the parent's lifecycle — when the
 * parent membership ends, the referral must end too. Otherwise a
 * referred patient walks around with a "Member · Active" badge in the
 * SPA after the underlying Gold card has lapsed, and the discount
 * gates that key off the badge silently keep granting member rates.
 *
 * Two cascade paths exist in the codebase, both pinned here:
 *
 *   1. Natural expiry — the daily `memberships:expire` cron flips
 *      `active=0` on every row whose `end_date < today`. Since
 *      referrals are created with end_date copied from the parent
 *      (`PatientService::addReferral`), parent + referral always
 *      flip on the SAME day. That's the "referral inherits parent
 *      end_date at creation" invariant.
 *
 *   2. Manual cancel — `MembershipService::cancelMembership` finds
 *      every referral with `parent_membership_code = <parent.code>`
 *      and nulls out their patient_id / start_date / end_date /
 *      assigned_at, effectively un-assigning them.
 *
 * Manual edit propagation — closes the gap that used to exist
 * pre-MembershipObserver: editing a parent's end_date now mirrors
 * the new date onto every referral via the `updated` event hook
 * (`MembershipObserver::updated`). Pinned by the
 * `parent_end_date_edit_*` tests below. The alternative "compute
 * referral effective end as min(own, parent) at read time" was
 * rejected because it would require touching every read site
 * (cron, badge, discount gates, datatable resource) — write-time
 * propagation keeps reads dumb.
 *
 * Anchor case: code CA4878 in production — patient 218169 (parent
 * Gold) and patient 218235 Muhammad Tahir (referral). Both share
 * end_date 2027-04-06 and both will flip when the date arrives.
 */
class MembershipReferralExpiryTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private PatientService $patientService;

    private MembershipService $membershipService;

    private int $goldTypeId;

    private int $parentPatientId;

    private int $referralPatientId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();

        // PatientService::addReferral writes Auth::id() to
        // memberships.created_by (NOT NULL). Without an
        // authenticated actor the insert violates the constraint
        // and the test crashes before exercising the rule we care
        // about — so we actingAs a synthetic admin first.
        $this->actingAsAdmin();

        $this->patientService = app(PatientService::class);
        $this->membershipService = app(MembershipService::class);

        // The "Gold Membership" type is referenced by name (case-
        // insensitive) inside PatientService::addReferral as the
        // gate for who can have referrals. Pin the exact spelling
        // so the test exercises the same branch the production
        // code does.
        $this->goldTypeId = (int) DB::table('membership_types')->insertGetId([
            'name' => 'Gold Membership',
            'period' => 12,
            'amount' => 0,
            'active' => 1,
            'created_by' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->parentPatientId = $this->makePatient('Parent Cardholder', '+923001110000');
        $this->referralPatientId = $this->makePatient('Referred Patient', '+923002220000');
    }

    private function makePatient(string $name, string $phone): int
    {
        return (int) DB::table('users')->insertGetId([
            'name' => $name,
            'email' => strtolower(str_replace([' ', '+'], ['.', ''], $name)).'@test.local',
            'password' => bcrypt('test'),
            'phone' => $phone,
            'user_type_id' => 3, // patient global scope
            'account_id' => 1,
            'active' => 1,
            'main_account' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Build a parent Gold Membership row — the anchor that the
     * referral will hang off of.
     */
    private function makeParentMembership(string $code, ?Carbon $endDate = null): Membership
    {
        $end = $endDate ?? Carbon::now()->addYear();
        return Membership::create([
            'code' => $code,
            'membership_type_id' => $this->goldTypeId,
            'patient_id' => $this->parentPatientId,
            'start_date' => Carbon::now()->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'active' => 1,
            'created_by' => 1,
            'is_referral' => 0,
            'parent_membership_code' => null,
            'assigned_at' => Carbon::now()->format('Y-m-d'),
        ]);
    }

    // =========================================================================
    // Invariant 1 — referral inherits parent's end_date at creation
    // =========================================================================

    public function test_referral_creation_copies_parent_end_date_so_natural_expiry_aligns(): void
    {
        // The whole "they expire together" guarantee depends on
        // this single line in PatientService::addReferral. If a
        // future refactor recomputes end_date from `now() +
        // type.period` instead of copying the parent's, parent
        // and referral can drift apart and only one will expire
        // when the date arrives.
        $parentEnd = Carbon::now()->addMonths(8);
        $parent = $this->makeParentMembership('CA-TEST-1', $parentEnd);

        $result = $this->patientService->addReferral($this->referralPatientId, 'CA-TEST-1');

        $this->assertTrue($result['status']);
        $referral = Membership::where('code', 'CA-TEST-1')->where('is_referral', 1)->first();
        $this->assertNotNull($referral);
        $this->assertSame($parent->end_date, $referral->end_date);
        $this->assertSame($parent->code, $referral->parent_membership_code);
    }

    public function test_referral_creation_is_rejected_when_parent_is_already_expired(): void
    {
        // Adding a referral to an expired parent must be refused
        // — the message is operator-facing and pinned so a copy
        // change doesn't drift away from the documented business
        // rule.
        $this->makeParentMembership('CA-TEST-2', Carbon::now()->subDay());

        $result = $this->patientService->addReferral($this->referralPatientId, 'CA-TEST-2');

        $this->assertFalse($result['status']);
        $this->assertStringContainsString('expired', strtolower($result['message']));
    }

    public function test_referral_creation_is_rejected_for_non_gold_membership_types(): void
    {
        // Referrals are a Gold-tier feature. Allowing them on
        // Student or any other type would change the discount
        // economics — pinned to the explicit name check.
        $studentTypeId = (int) DB::table('membership_types')->insertGetId([
            'name' => 'Student Membership',
            'period' => 6,
            'amount' => 0,
            'active' => 1,
            'created_by' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Membership::create([
            'code' => 'ST-TEST-1',
            'membership_type_id' => $studentTypeId,
            'patient_id' => $this->parentPatientId,
            'start_date' => Carbon::now()->format('Y-m-d'),
            'end_date' => Carbon::now()->addMonths(6)->format('Y-m-d'),
            'active' => 1,
            'created_by' => 1,
            'is_referral' => 0,
        ]);

        $result = $this->patientService->addReferral($this->referralPatientId, 'ST-TEST-1');

        $this->assertFalse($result['status']);
        $this->assertStringContainsString('Gold', $result['message']);
    }

    // =========================================================================
    // Invariant 2 — natural expiry: cron flips parent + referral together
    // =========================================================================

    public function test_daily_expire_cron_flips_BOTH_parent_and_referral_when_their_shared_end_date_passes(): void
    {
        // Anchor case from production CA4878 — both rows share
        // end_date and both must flip when the cron runs the day
        // after expiry. If the referral persists past expiry, its
        // patient still reads as a "Member · Active" in the SPA
        // and discount gates that key on the badge silently grant
        // member rates after the card has lapsed.
        //
        // Time-travel: create both rows with end_date in the
        // (real) future so the addReferral guard ("don't add to
        // an expired parent") doesn't reject us, then advance
        // Carbon's "now" past that date and run the cron.
        $sharedEnd = Carbon::now()->addMonths(3);
        $parent = $this->makeParentMembership('CA-TEST-3', $sharedEnd);
        $referralResult = $this->patientService->addReferral($this->referralPatientId, 'CA-TEST-3');
        $this->assertTrue($referralResult['status'], $referralResult['message'] ?? 'addReferral failed');

        // Pre-condition — both are still active=1, end_date in
        // the future. The cron is what will flip them once "now"
        // passes that date.
        $this->assertSame(2, Membership::where('code', 'CA-TEST-3')->where('active', 1)->count());

        Carbon::setTestNow($sharedEnd->copy()->addDay());
        $this->artisan('memberships:expire')->assertExitCode(0);
        Carbon::setTestNow();

        $this->assertSame(0, Membership::where('code', 'CA-TEST-3')->where('active', 1)->count());
        $parent->refresh();
        $this->assertSame(0, (int) $parent->active);
    }

    public function test_daily_expire_cron_does_NOT_touch_rows_whose_end_date_is_still_in_the_future(): void
    {
        // Defensive — a rogue cron run must not collapse healthy
        // memberships. Pin "rows with end_date >= today stay
        // active=1" so any future "let's also expire X" PR has
        // to update this test deliberately.
        $this->makeParentMembership('CA-TEST-4', Carbon::now()->addMonths(6));
        $this->patientService->addReferral($this->referralPatientId, 'CA-TEST-4');

        $this->artisan('memberships:expire')->assertExitCode(0);

        $this->assertSame(2, Membership::where('code', 'CA-TEST-4')->where('active', 1)->count());
    }

    // =========================================================================
    // Invariant 3 — manual cancel cascades parent → referral
    // =========================================================================

    public function test_manual_cancel_on_parent_unassigns_every_referral_under_it(): void
    {
        // The cascade in MembershipService::cancelMembership.
        // Referral keeps the row (audit trail) but loses
        // patient_id / dates — effectively un-assigned, so it
        // doesn't render on the patient any more.
        $parent = $this->makeParentMembership('CA-TEST-5');
        $this->patientService->addReferral($this->referralPatientId, 'CA-TEST-5');

        // Sanity — referral is assigned and dated.
        $referralBefore = Membership::where('code', 'CA-TEST-5')->where('is_referral', 1)->first();
        $this->assertNotNull($referralBefore->patient_id);
        $this->assertNotNull($referralBefore->end_date);

        $this->membershipService->cancelMembership($this->parentPatientId);

        $referralAfter = $referralBefore->fresh();
        $this->assertNull($referralAfter->patient_id);
        $this->assertNull($referralAfter->start_date);
        $this->assertNull($referralAfter->end_date);
        $this->assertNull($referralAfter->assigned_at);
    }

    public function test_manual_cancel_on_a_referral_does_NOT_cascade_back_to_the_parent(): void
    {
        // Direction matters — a referral cancellation must NOT
        // deactivate the parent (that would be a customer-
        // affecting bug). Cascade is one-way: parent → referrals.
        $parent = $this->makeParentMembership('CA-TEST-6');
        $this->patientService->addReferral($this->referralPatientId, 'CA-TEST-6');

        $this->membershipService->cancelMembership($this->referralPatientId);

        $parentAfter = $parent->fresh();
        $this->assertSame(1, (int) $parentAfter->active);
        $this->assertSame($this->parentPatientId, (int) $parentAfter->patient_id);
        $this->assertNotNull($parentAfter->end_date);
    }

    // =========================================================================
    // Invariant 4 — manual end_date edit on parent propagates to referrals
    // =========================================================================

    public function test_parent_end_date_edit_propagates_to_every_referral_under_it(): void
    {
        // The MembershipObserver hook. Without it, an admin who
        // extends a Gold parent to 2028 would leave the referral
        // stuck on the original 2027 — silently expiring early.
        // Conversely, an admin shortening the parent to next week
        // would leave the referral active through its old date,
        // which would silently keep granting member rates.
        $parent = $this->makeParentMembership('CA-TEST-7', Carbon::now()->addMonths(8));
        $this->patientService->addReferral($this->referralPatientId, 'CA-TEST-7');
        $referralBefore = Membership::where('code', 'CA-TEST-7')->where('is_referral', 1)->first();
        $originalEnd = $referralBefore->end_date;

        // Admin extends the parent to a date far in the future.
        $newEnd = Carbon::now()->addMonths(20)->format('Y-m-d');
        $parent->update(['end_date' => $newEnd]);

        $referralAfter = $referralBefore->fresh();
        $this->assertNotSame($originalEnd, $referralAfter->end_date);
        $this->assertSame($newEnd, $referralAfter->end_date);
    }

    public function test_parent_end_date_shortening_propagates_so_referral_expires_with_parent(): void
    {
        // The other direction — early termination. Critical for
        // refunded plans where the Gold card is being clawed back:
        // referrals must lose access on the same day, not after.
        $parent = $this->makeParentMembership('CA-TEST-8', Carbon::now()->addMonths(8));
        $this->patientService->addReferral($this->referralPatientId, 'CA-TEST-8');
        $referralBefore = Membership::where('code', 'CA-TEST-8')->where('is_referral', 1)->first();

        $newEnd = Carbon::now()->addDays(7)->format('Y-m-d');
        $parent->update(['end_date' => $newEnd]);

        $this->assertSame($newEnd, $referralBefore->fresh()->end_date);
    }

    public function test_referral_end_date_edit_does_NOT_propagate_BACK_to_the_parent(): void
    {
        // Direction asymmetry — same as the manual cancel test.
        // A direct edit on a referral row must NOT push back up
        // to the parent. If it did, one referral admin could
        // silently shorten or extend the Gold parent for everyone
        // sharing the same code.
        $parent = $this->makeParentMembership('CA-TEST-9', Carbon::now()->addMonths(8));
        $originalParentEnd = $parent->end_date;
        $this->patientService->addReferral($this->referralPatientId, 'CA-TEST-9');
        $referral = Membership::where('code', 'CA-TEST-9')->where('is_referral', 1)->first();

        // Edit the referral's end_date directly.
        $referral->update(['end_date' => Carbon::now()->addDays(3)->format('Y-m-d')]);

        $this->assertSame($originalParentEnd, $parent->fresh()->end_date);
    }

    public function test_parent_save_without_end_date_change_does_not_touch_referrals(): void
    {
        // Defensive — the observer must only fire on actual
        // end_date changes, not on every save. Without the
        // `wasChanged('end_date')` guard, every patient_id /
        // updated_by edit on a parent would issue a wasted
        // bulk UPDATE on its referrals (and noise up the audit
        // log).
        $parent = $this->makeParentMembership('CA-TEST-10', Carbon::now()->addMonths(8));
        $this->patientService->addReferral($this->referralPatientId, 'CA-TEST-10');
        $referral = Membership::where('code', 'CA-TEST-10')->where('is_referral', 1)->first();
        $referralUpdatedAtBefore = $referral->updated_at;

        // Sleep one second so any spurious touch would be
        // visible in updated_at. Bumping a non-date field on
        // the parent shouldn't propagate.
        sleep(1);
        $parent->update(['updated_by' => 1]);

        $this->assertEquals(
            $referralUpdatedAtBefore?->toDateTimeString(),
            $referral->fresh()->updated_at?->toDateTimeString(),
        );
    }

    // =========================================================================
    // Invariant 5 — referralInfo() powers the SPA "X of 2 used" hint
    // =========================================================================

    public function test_referral_info_reports_count_and_flips_limit_reached_at_two(): void
    {
        // Backs GET /api/patients/membership/referral-count. The dialog
        // disables submit when limit_reached, so the threshold must match
        // the MAX_REFERRALS_PER_CODE used by addReferral (2). If the two
        // drift apart, the UI would either block a legal referral or invite
        // a doomed POST.
        $this->makeParentMembership('CA-RC-1', Carbon::now()->addMonths(8));

        // No referrals yet — count 0, under the limit.
        $info = $this->patientService->referralInfo('CA-RC-1');
        $this->assertSame(0, $info['count']);
        $this->assertSame(2, $info['max']);
        $this->assertFalse($info['limit_reached']);

        // First referral — count 1, still under the limit.
        $this->patientService->addReferral($this->referralPatientId, 'CA-RC-1');
        $info = $this->patientService->referralInfo('CA-RC-1');
        $this->assertSame(1, $info['count']);
        $this->assertFalse($info['limit_reached']);

        // Second referral — count 2, limit now reached.
        $secondReferral = $this->makePatient('Second Referral', '+923003330000');
        $this->patientService->addReferral($secondReferral, 'CA-RC-1');
        $info = $this->patientService->referralInfo('CA-RC-1');
        $this->assertSame(2, $info['count']);
        $this->assertTrue($info['limit_reached']);
    }
}
