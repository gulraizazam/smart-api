<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ActivityLogTier;
use App\Models\Activity;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Classifies existing `activities` rows with a NULL `log_tier` by mapping
 * their `activity_type` string to an ActivityLogTier.
 *
 * One-time backfill, safe to rerun — only touches rows still NULL. Rows
 * whose activity_type is unknown to ActivityLogTier::classify() stay NULL
 * and are reported in the summary so a human can triage them. They remain
 * protected from pruning.
 *
 * Uses chunkById() on the primary key (never the column being updated)
 * and updates each chunk inside DB::transaction() with a single bulk
 * CASE/WHEN statement to keep binlog pressure bounded.
 */
class BackfillActivityLogTiers extends Command
{
    protected $signature = 'activities:backfill-tiers
                            {--chunk=2000 : Rows per chunk}
                            {--dry-run : Report what would change without writing}';

    protected $description = 'Classify existing activities rows into retention tiers (clinical/financial/operational).';

    public function handle(): int
    {
        $chunkSize = (int) $this->option('chunk');
        $dryRun = (bool) $this->option('dry-run');

        if ($chunkSize < 1 || $chunkSize > 10_000) {
            $this->error('chunk must be between 1 and 10000');

            return self::INVALID;
        }

        $totalSeen = 0;
        $totalUpdated = 0;
        $unclassified = [];

        Activity::whereNull('log_tier')
            ->orderBy('id')
            ->chunkById($chunkSize, function ($rows) use ($dryRun, &$totalSeen, &$totalUpdated, &$unclassified): void {
                $byTier = [];

                foreach ($rows as $row) {
                    $totalSeen++;
                    $tier = ActivityLogTier::classify($row->activity_type, $row->action);

                    if ($tier === null) {
                        $key = $row->activity_type
                            ?? ($row->action !== null ? "(action:{$row->action})" : '(null)');
                        $unclassified[$key] = ($unclassified[$key] ?? 0) + 1;

                        continue;
                    }

                    $byTier[$tier->value][] = (int) $row->id;
                }

                if ($dryRun) {
                    return;
                }

                foreach ($byTier as $tierValue => $ids) {
                    foreach (array_chunk($ids, 1000) as $slice) {
                        DB::transaction(function () use ($tierValue, $slice, &$totalUpdated): void {
                            $affected = DB::table('activities')
                                ->whereIn('id', $slice)
                                ->whereNull('log_tier')
                                ->update(['log_tier' => $tierValue]);

                            $totalUpdated += $affected;
                        });
                    }
                }
            });

        $this->info(sprintf(
            '[%s] seen=%d updated=%d unclassified_types=%d',
            $dryRun ? 'DRY-RUN' : 'DONE',
            $totalSeen,
            $totalUpdated,
            array_sum($unclassified),
        ));

        if ($unclassified !== []) {
            $this->warn('Unclassified activity_type counts (rows left NULL, safe from pruning):');
            arsort($unclassified);
            foreach ($unclassified as $type => $count) {
                $this->line(sprintf('  %-40s %d', $type, $count));
            }
        }

        Log::info('activities.backfill_tiers.completed', [
            'event' => 'activities.backfill_tiers.completed',
            'dry_run' => $dryRun,
            'seen' => $totalSeen,
            'updated' => $totalUpdated,
            'unclassified' => $unclassified,
        ]);

        return self::SUCCESS;
    }
}
