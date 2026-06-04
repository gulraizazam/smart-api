<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Re-align drifted referral end_dates to their parent Gold card.
 *
 * Background:
 *   A referral membership (`memberships.is_referral = 1`,
 *   `parent_membership_code` set) inherits its parent's lifecycle.
 *   `PatientService::addReferral` copies the parent's end_date at
 *   creation, and `MembershipObserver::updated` mirrors any later
 *   edit to the parent's end_date onto the referrals. But the
 *   observer only exists for edits made AFTER it was deployed — any
 *   parent whose end_date was shortened/extended BEFORE the observer
 *   left its referrals stranded on the original date.
 *
 *   Effect of the drift: the referred patient keeps a "Member ·
 *   Active" badge and member discount rates until the referral's
 *   (stale, longer) end_date, i.e. past the real card's expiry — the
 *   exact failure mode pinned by
 *   tests/Feature/Plan/MembershipReferralExpiryTest.php.
 *
 * Detection (the "assigned parent" disambiguates duplicate codes —
 * a code can have a leftover unsold STOCK parent row with a different
 * date; we only trust the parent that is actually assigned to a
 * patient):
 *   referral is active, is_referral = 1, not soft-deleted, and its
 *   end_date differs (NULL-safe) from the end_date of the single
 *   active, ASSIGNED (patient_id IS NOT NULL), non-referral parent
 *   sharing its code.
 *
 * Action:
 *   Set the referral's end_date to the assigned parent's end_date —
 *   identical to what MembershipObserver does on a live edit (one-way,
 *   parent -> referral; we never touch the parent). If a code somehow
 *   resolves to more than one assigned parent with conflicting dates,
 *   the referral is SKIPPED and logged for manual review rather than
 *   guessed.
 *
 * Two-step (collect ids -> update by id) so we don't depend on
 * JOIN-in-UPDATE syntax, which differs across MariaDB versions and
 * would silently no-op on some — same reasoning as
 * 2026_05_02_120000_release_ghost_memberships_from_deleted_plans.
 *
 * Idempotent: after the run the referral end_date equals the parent's,
 * so a second run finds zero candidates.
 *
 * Coexistence: crm2 reads memberships.end_date but this only CORRECTS
 * values to be internally consistent — no column/shape change — so it
 * is additive and safe for the shared DB.
 *
 * Down: irreversible by design — the prior (wrong) date is not worth
 * restoring; the aligned state is the canonical one.
 */
return new class extends Migration
{
    public function up(): void
    {
        $today = now()->toDateTimeString();

        // Referral -> its active, ASSIGNED parent's end_date.
        // `<=> = 0` selects rows whose end_dates DIFFER (NULL-safe).
        $candidates = DB::select(
            'SELECT r.id AS ref_id, p.end_date AS parent_end '
            . 'FROM memberships r '
            . 'JOIN memberships p '
            . "  ON p.code = r.parent_membership_code "
            . " AND p.is_referral = 0 AND p.active = 1 "
            . " AND p.patient_id IS NOT NULL AND p.deleted_at IS NULL "
            . 'WHERE r.is_referral = 1 AND r.active = 1 AND r.deleted_at IS NULL '
            . '  AND (r.end_date <=> p.end_date) = 0'
        );

        // Group by referral so we can detect (and skip) the rare case
        // of a code resolving to >1 assigned parent with conflicting
        // dates — we realign only when the target date is unambiguous.
        $endsByRef = [];
        foreach ($candidates as $row) {
            $endsByRef[$row->ref_id][] = $row->parent_end;
        }

        $realigned = 0;
        $skipped = [];
        foreach ($endsByRef as $refId => $ends) {
            $distinct = array_values(array_unique($ends));
            if (count($distinct) !== 1) {
                $skipped[] = (int) $refId;
                continue;
            }
            DB::table('memberships')
                ->where('id', $refId)
                ->update([
                    'end_date'   => $distinct[0],
                    'updated_at' => $today,
                ]);
            $realigned++;
        }

        \Log::info('realign_referral_end_dates_to_parent: realigned referrals', [
            'realigned'      => $realigned,
            'skipped_ref_ids' => $skipped,
        ]);
    }

    public function down(): void
    {
        // Irreversible — see class docblock.
    }
};
