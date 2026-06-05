<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Clear the dangling parent pointer on orphan referral rows.
 *
 * Background:
 *   A handful of referral rows (`is_referral = 1`) carry a
 *   `parent_membership_code` that matches NO existing non-referral
 *   parent — the parent Gold row was hard-deleted by a legacy delete
 *   path that didn't cascade (the same class of residue swept by
 *   2026_05_02_120000_release_ghost_memberships_from_deleted_plans).
 *   The known cases are already `active = 0`, so they grant and expire
 *   nothing — this is cosmetic cleanup of a pointer to a row that no
 *   longer exists.
 *
 * Detection:
 *   is_referral = 1, not soft-deleted, parent_membership_code IS NOT
 *   NULL, and there is NO non-referral, non-deleted parent with that
 *   code.
 *
 * Action:
 *   Set parent_membership_code = NULL so the row no longer advertises
 *   a parent that isn't there. We do NOT soft-delete the row (that
 *   would change crm2's membership list counts) and do NOT touch
 *   `active`.
 *
 * Idempotent: once the pointer is NULL the row is no longer matched.
 *
 * Coexistence: only nulls a dead pointer on already-inactive rows; no
 * column/shape change; additive + safe for the shared DB.
 *
 * Down: irreversible by design — the original (already-dangling) code
 * is not worth restoring.
 */
return new class extends Migration
{
    public function up(): void
    {
        $today = now()->toDateTimeString();

        $orphanIds = DB::table('memberships as r')
            ->where('r.is_referral', 1)
            ->whereNull('r.deleted_at')
            ->whereNotNull('r.parent_membership_code')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('memberships as p')
                    ->whereColumn('p.code', 'r.parent_membership_code')
                    ->where('p.is_referral', 0)
                    ->whereNull('p.deleted_at');
            })
            ->pluck('r.id')
            ->all();

        $cleared = 0;
        if (! empty($orphanIds)) {
            $cleared = DB::table('memberships')
                ->whereIn('id', $orphanIds)
                ->update([
                    'parent_membership_code' => null,
                    'updated_at'             => $today,
                ]);
        }

        \Log::info('null_orphan_referral_parent_code: cleared dangling parent pointers', [
            'cleared' => $cleared,
        ]);
    }

    public function down(): void
    {
        // Irreversible — see class docblock.
    }
};
