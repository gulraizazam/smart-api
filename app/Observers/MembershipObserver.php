<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Membership;
use Illuminate\Support\Facades\DB;

/**
 * Membership lifecycle propagation: when a parent (non-referral)
 * membership's `end_date` is edited post-creation, mirror the new
 * date onto every referral that hangs off the parent's code.
 *
 * Why: referrals inherit their parent's lifecycle. The data model
 * already enforces this at *creation* (`PatientService::addReferral`
 * copies the parent's start/end dates) and at *manual cancel*
 * (`MembershipService::cancelMembership` cascades). The remaining
 * gap was a manual EDIT to the parent's end_date — admin extends
 * the parent to 2028 but the referrals stay on 2027, or admin
 * shortens the parent to next week but the referrals keep their
 * original end_date and remain "active" past the parent's end.
 *
 * The downside of the alternative — computing referral effective
 * end as `min(own, parent)` at READ time — is that every site
 * that reads `memberships.end_date` (the daily expire cron, the
 * MembershipBadge tooltip, the discount gates, the
 * PlanDatatableResource formatter, etc.) would have to learn about
 * parents. Pushing the rule to *write* time keeps every existing
 * read site correct without modification, and write amplification
 * is bounded (MAX_REFERRALS_PER_CODE = 5) so the perf cost is
 * negligible.
 *
 * Direction is one-way only: parent → referrals. A referral's own
 * end_date edit does NOT propagate "up" to the parent, mirroring
 * the existing cancel cascade asymmetry.
 *
 * Pinned by tests/Feature/Plan/MembershipReferralExpiryTest.php.
 */
class MembershipObserver
{
    public function updated(Membership $membership): void
    {
        // Only parents propagate. A referral row's end_date edit
        // shouldn't push back up to the parent — that would be a
        // customer-affecting bug (one referral admin could shorten
        // a Gold cardholder's expiry).
        if ((int) $membership->is_referral === 1) {
            return;
        }

        // Only act when end_date actually changed. Without this
        // guard, every save() on a parent (touch-updates,
        // patient_id changes, etc.) would issue a UPDATE on every
        // referral — wasted writes and noisier audit logs.
        if (! $membership->wasChanged('end_date')) {
            return;
        }

        // Bypass the model layer for the cascade UPDATE — using
        // Eloquent here would re-fire `updated` events on each
        // referral row, and the next event handler would see
        // `wasChanged('end_date') === true` on a referral
        // (`is_referral === 1`), which our guard above already
        // rejects, but we still pay for the event dispatch and
        // the per-row save overhead. DB::table is a single
        // bulk UPDATE, observed-event-free, and matches the
        // pattern `MembershipService::cancelMembership` already
        // uses for its cascade.
        DB::table('memberships')
            ->where('parent_membership_code', $membership->code)
            ->where('is_referral', 1)
            ->whereNull('deleted_at')
            ->update([
                'end_date' => $membership->end_date,
                'updated_at' => now(),
            ]);
    }
}
