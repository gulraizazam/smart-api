<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Release "ghost" membership assignments left behind by plan deletes.
 *
 * Background:
 *   The legacy `PlanService::deletePlan` only set `packages.deleted_at`.
 *   It did NOT cascade to `memberships`, so any membership row that had
 *   been assigned (`memberships.patient_id` populated) by a since-
 *   deleted plan kept pointing at the patient. That meant:
 *     • Patient list / plans datatable kept rendering a "Member" badge
 *       (e.g. "Student Member · CA0443") for a card the patient no
 *       longer holds.
 *     • The membership code was effectively unusable — it wasn't free
 *       to be re-assigned to another patient.
 *     • Discount-eligibility logic could see the orphan code as the
 *       patient's "active" tier (until end_date check fails).
 *   Forward-looking fix: `PlanService::releaseMembershipFromPlan` now
 *   runs before `$package->delete()` in both single- and bulk-delete
 *   paths, so deletions made AFTER this release are clean.
 *   Backward-looking fix: this migration sweeps the historical residue.
 *
 * Detection:
 *   A membership row is a "ghost" when ALL of these are true:
 *     1. `memberships.patient_id IS NOT NULL`
 *        — the row is assigned to someone.
 *     2. EVERY `package_bundles` row that references this
 *        `memberships.id` belongs to a soft-deleted package.
 *        — i.e. there is no live plan still attaching this code.
 *     3. The membership row itself isn't soft-deleted.
 *   Condition (2) protects against the case where a patient
 *   legitimately holds multiple plans against the same code — we only
 *   release when there's truly no live plan left. The `NOT EXISTS`
 *   subquery encodes that.
 *
 * Action:
 *   For each ghost:
 *     • Reset `patient_id`, `start_date`, `end_date`, `assigned_at`
 *       to NULL. The code is back in the assignable pool.
 *     • Keep `active = 1` — the code itself is still a valid catalog
 *       entry, never been voided.
 *   Also soft-deletes any orphaned `student_verifications` row keyed
 *   to a soft-deleted package_id (same family of orphaned data).
 *
 * Down: irreversible by design — we don't have the historical
 * patient_id / dates to put back. Backup the DB before running if a
 * full rollback is needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        $today = now()->toDateTimeString();

        // 1. Release the ghost membership assignments.
        $released = DB::table('memberships as m')
            ->whereNotNull('m.patient_id')
            ->whereNull('m.deleted_at')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('package_bundles as pb')
                    ->join('packages as p', 'p.id', '=', 'pb.package_id')
                    ->whereColumn('pb.membership_code_id', 'm.id')
                    ->whereNull('pb.deleted_at')
                    ->whereNull('p.deleted_at');
            })
            ->whereExists(function ($q) {
                // Only touch codes that WERE attached to at least one
                // package (now deleted). Avoids zeroing out codes that
                // were assigned by some other (non-package) flow.
                $q->select(DB::raw(1))
                    ->from('package_bundles as pb')
                    ->whereColumn('pb.membership_code_id', 'm.id');
            })
            ->update([
                'patient_id'  => null,
                'start_date'  => null,
                'end_date'    => null,
                'assigned_at' => null,
                'updated_at'  => $today,
            ]);

        \Log::info('release_ghost_memberships_from_deleted_plans: released codes', [
            'count' => $released,
        ]);

        // 2. Soft-delete orphan student_verifications whose package is
        //    soft-deleted. Two-step (collect ids → update by id) so
        //    we don't depend on JOIN-in-UPDATE syntax, which differs
        //    across MariaDB versions and would silently no-op on some.
        //    Files on disk are not touched here — that requires the
        //    StudentVerificationService and its Auth context, which
        //    migrations don't have. The forward-looking
        //    `releaseMembershipFromPlan` handles file cleanup for new
        //    deletes; existing orphans are cheap (≤10MB each) and can
        //    be swept later via a separate one-off script if storage
        //    pressure demands it.
        $orphanIds = DB::table('student_verifications')
            ->join('packages', 'packages.id', '=', 'student_verifications.package_id')
            ->whereNotNull('packages.deleted_at')
            ->whereNull('student_verifications.deleted_at')
            ->pluck('student_verifications.id')
            ->all();

        $orphanedVerifications = 0;
        if (! empty($orphanIds)) {
            $orphanedVerifications = DB::table('student_verifications')
                ->whereIn('id', $orphanIds)
                ->update([
                    'deleted_at' => $today,
                    'updated_at' => $today,
                ]);
        }

        \Log::info('release_ghost_memberships_from_deleted_plans: archived orphan verifications', [
            'count' => $orphanedVerifications,
        ]);
    }

    public function down(): void
    {
        // Irreversible — see class docblock.
    }
};
