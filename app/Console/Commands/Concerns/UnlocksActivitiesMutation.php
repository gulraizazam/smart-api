<?php

declare(strict_types=1);

namespace App\Console\Commands\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Helper for authorized command classes (ArchiveAndPruneActivities,
 * ReclassifyActivityLogTiers) that legitimately need to DELETE or UPDATE
 * rows in `activities` or `activities_archive`.
 *
 * Sets the MariaDB session variable the tamper-evident triggers check before
 * allowing a mutation, then unsets it after — so a subsequent code path
 * in the same connection can't piggyback on the unlocked state.
 *
 * Usage:
 *
 *     $this->withActivitiesMutationAllowed(function (): void {
 *         DB::table('activities')->whereIn('id', $ids)->delete();
 *     });
 */
trait UnlocksActivitiesMutation
{
    protected function withActivitiesMutationAllowed(callable $callback): mixed
    {
        DB::statement('SET @activities_mutation_allowed = 1');

        try {
            return $callback();
        } finally {
            DB::statement('SET @activities_mutation_allowed = NULL');
        }
    }
}
