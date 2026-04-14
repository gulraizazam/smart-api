<?php

declare(strict_types=1);

use App\Enums\ActivityLogTier;

/**
 * HIPAA-aligned retention policy per activity-log tier.
 *
 * Pakistan is not legally subject to HIPAA; this policy follows HIPAA §164.316(b)(2)(i)
 * (6-year minimum for audit logs) as an internal best-practice alignment.
 *
 * hot_days  = rows older than this (by created_at) are moved from
 *             `activities` into `activities_archive`.
 * cold_days = rows in `activities_archive` older than this (by archived_at)
 *             are permanently deleted. Set to 0 (or null) to NEVER delete
 *             — the append-only behavior HIPAA expects for PHI audit logs.
 *
 * Under HIPAA alignment:
 *   - phi_audit: PHI-touching events retained forever (archive only, no delete)
 *   - security_audit: auth/privilege events retained forever
 *   - hr_standard: employment records deleted at 2 years (not PHI; labor law)
 *   - hr_candidates_rejected: 6 months (PII minimization for non-hired candidates)
 *
 * Transitional tier keys (clinical, financial, operational, security) are
 * kept so the archive command can process rows written BEFORE the
 * reclassification command runs. They map to the same retention as
 * phi_audit / security_audit respectively. After the reclassification
 * command has been run and all rows carry the new tier values, the
 * transitional entries can be removed.
 *
 * Unmapped rows (tier IS NULL) are never touched. See App\Enums\ActivityLogTier::classify().
 */
return [

    'tiers' => [

        // Active HIPAA-aligned tiers

        ActivityLogTier::PhiAudit->value => [
            'hot_days' => (int) env('ACTIVITY_RETENTION_PHI_HOT_DAYS', 540),
            'cold_days' => 0, // append-only — never delete
        ],

        ActivityLogTier::SecurityAudit->value => [
            'hot_days' => (int) env('ACTIVITY_RETENTION_SECURITY_HOT_DAYS', 540),
            'cold_days' => 0, // append-only — never delete
        ],

        ActivityLogTier::HrStandard->value => [
            'hot_days' => (int) env('ACTIVITY_RETENTION_HR_HOT_DAYS', 365),
            'cold_days' => (int) env('ACTIVITY_RETENTION_HR_COLD_DAYS', 365),
        ],

        ActivityLogTier::HrCandidatesRejected->value => [
            'hot_days' => (int) env('ACTIVITY_RETENTION_HR_REJECTED_HOT_DAYS', 180),
            'cold_days' => 0,
            // NOTE: hot_days of 180 with cold_days 0 means hard-delete at 180d.
            // Intentional for rejected-candidate PII minimization.
            'hard_delete_without_archive' => true,
        ],

        // Transitional — pre-reclassification rows in existing DB carry these
        // values. Each maps to the equivalent HIPAA tier for retention purposes.
        // Remove once `php artisan activities:reclassify-tiers` has updated
        // the entire DB.

        'clinical' => [
            'hot_days' => 540,
            'cold_days' => 0,
        ],

        'financial' => [
            'hot_days' => 540,
            'cold_days' => 0,
        ],

        'operational' => [
            'hot_days' => 540,
            'cold_days' => 0,
        ],

        'security' => [
            'hot_days' => 540,
            'cold_days' => 0,
        ],

    ],

    'chunk_size' => (int) env('ACTIVITY_RETENTION_CHUNK_SIZE', 5000),

    'max_rows_per_run' => (int) env('ACTIVITY_RETENTION_MAX_ROWS_PER_RUN', 200_000),

];
