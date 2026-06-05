<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Retire leftover unsold STOCK duplicates of an assigned parent code.
 *
 * Background:
 *   A membership `code` is supposed to identify one parent card. A few
 *   codes ended up with TWO active, non-referral ("parent") rows:
 *     - the real one, assigned to a patient (patient_id set), and
 *     - a leftover unsold STOCK card (patient_id NULL) printed with
 *       the same code and never retired.
 *   This makes the referral expiry cascade ambiguous: a referral
 *   looking up its parent by code can match the wrong (stock) row,
 *   which carries a different/NULL end_date — the root cause behind
 *   the drifted referral dates fixed in the sibling migration
 *   2026_06_04_120000_realign_referral_end_dates_to_parent.
 *
 * Detection:
 *   codes that have > 1 active, non-referral, non-deleted parent row.
 *   For each such code, the duplicate to retire is the UNASSIGNED
 *   (patient_id IS NULL) stock copy.
 *
 * Action:
 *   Set `active = 0` on the unassigned stock copies — NOT a hard
 *   delete (crm2 may still list the row) and never the real
 *   cardholder. A guard requires at least one ASSIGNED active parent
 *   to remain for the code before retiring its stock copies; codes
 *   that are all-stock or otherwise ambiguous are SKIPPED and logged
 *   for manual review rather than guessed at.
 *
 * Idempotent: once the stock copies are inactive the code no longer
 * has > 1 active parent, so a second run finds nothing.
 *
 * Coexistence: flipping a stock card to inactive is exactly what
 * crm2's own "deactivate membership" does; reversible; no schema
 * change. Additive + safe for the shared DB. Run at low traffic and
 * re-run scripts/coexistence-check.sh after.
 *
 * Down: irreversible by design — we don't record which rows we flipped
 * vs. those already inactive.
 */
return new class extends Migration
{
    public function up(): void
    {
        $today = now()->toDateTimeString();

        $dupCodes = DB::table('memberships')
            ->where('is_referral', 0)
            ->where('active', 1)
            ->whereNull('deleted_at')
            ->groupBy('code')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('code')
            ->all();

        $toDeactivate = [];
        $skipped = [];
        foreach ($dupCodes as $code) {
            $parents = DB::table('memberships')
                ->where('code', $code)
                ->where('is_referral', 0)
                ->where('active', 1)
                ->whereNull('deleted_at')
                ->get(['id', 'patient_id']);

            $assigned = $parents->whereNotNull('patient_id');
            $stock = $parents->whereNull('patient_id');

            // Only retire stock copies when at least one assigned
            // parent will remain. Never deactivate the last active
            // parent for a code.
            if ($assigned->count() >= 1 && $stock->count() >= 1) {
                foreach ($stock as $row) {
                    $toDeactivate[] = (int) $row->id;
                }
            } else {
                $skipped[] = $code;
            }
        }

        $retired = 0;
        if (! empty($toDeactivate)) {
            $retired = DB::table('memberships')
                ->whereIn('id', $toDeactivate)
                ->update([
                    'active'     => 0,
                    'updated_at' => $today,
                ]);
        }

        \Log::info('retire_duplicate_stock_membership_parents: retired stock duplicates', [
            'retired'       => $retired,
            'skipped_codes' => $skipped,
        ]);
    }

    public function down(): void
    {
        // Irreversible — see class docblock.
    }
};
