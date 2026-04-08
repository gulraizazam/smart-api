<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ArchiveAuditTrails extends Command
{
    protected $signature = 'audit:archive
        {--months=12 : Archive records older than this many months}
        {--batch=5000 : Number of trail records to process per batch}
        {--dry-run : Show what would be archived without moving data}';

    protected $description = 'Archive old audit trail records to archive tables (safe, batched, reversible)';

    #[\Override]
    public function handle(): int
    {
        $months = (int) $this->option('months');
        $batchSize = (int) $this->option('batch');
        $dryRun = (bool) $this->option('dry-run');
        $cutoffDate = now()->subMonths($months)->format('Y-m-d H:i:s');

        $this->info("Archiving audit trails older than {$months} months (before {$cutoffDate})");

        // Count eligible records
        $trailCount = DB::table('audit_trails')
            ->where('created_at', '<', $cutoffDate)
            ->count();

        $changeCount = DB::table('audit_trail_changes')
            ->whereIn('audit_trail_id', function ($q) use ($cutoffDate) {
                $q->select('id')->from('audit_trails')->where('created_at', '<', $cutoffDate);
            })
            ->count();

        $this->info("Found {$trailCount} trails and {$changeCount} changes eligible for archival.");

        if ($trailCount === 0) {
            $this->info('Nothing to archive.');
            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn('Dry run — no data moved.');
            return self::SUCCESS;
        }

        if (!$this->confirm("Move {$trailCount} trails and {$changeCount} changes to archive tables?")) {
            $this->info('Cancelled.');
            return self::SUCCESS;
        }

        $totalTrails = 0;
        $totalChanges = 0;

        $this->withProgressBar($trailCount, function ($bar) use ($cutoffDate, $batchSize, &$totalTrails, &$totalChanges) {
            while (true) {
                // Get batch of trail IDs
                $trailIds = DB::table('audit_trails')
                    ->where('created_at', '<', $cutoffDate)
                    ->orderBy('id')
                    ->limit($batchSize)
                    ->pluck('id')
                    ->toArray();

                if (empty($trailIds)) {
                    break;
                }

                DB::transaction(function () use ($trailIds, &$totalTrails, &$totalChanges) {
                    // 1. Copy changes to archive
                    $changesMoved = DB::statement(
                        'INSERT INTO audit_trail_changes_archive SELECT * FROM audit_trail_changes WHERE audit_trail_id IN (' . implode(',', $trailIds) . ')'
                    );

                    $changesCount = DB::table('audit_trail_changes')
                        ->whereIn('audit_trail_id', $trailIds)
                        ->count();

                    // 2. Delete changes from source
                    DB::table('audit_trail_changes')
                        ->whereIn('audit_trail_id', $trailIds)
                        ->delete();

                    // 3. Copy trails to archive
                    DB::statement(
                        'INSERT INTO audit_trails_archive SELECT * FROM audit_trails WHERE id IN (' . implode(',', $trailIds) . ')'
                    );

                    // 4. Delete trails from source
                    DB::table('audit_trails')
                        ->whereIn('id', $trailIds)
                        ->delete();

                    $totalTrails += count($trailIds);
                    $totalChanges += $changesCount;
                });

                $bar->advance(count($trailIds));
            }
        });

        $this->newLine(2);
        $this->info("Archived {$totalTrails} trails and {$totalChanges} changes.");
        $this->info('Run OPTIMIZE TABLE audit_trails, audit_trail_changes; to reclaim disk space.');

        return self::SUCCESS;
    }
}
