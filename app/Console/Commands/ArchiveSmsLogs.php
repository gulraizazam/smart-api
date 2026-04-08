<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ArchiveSmsLogs extends Command
{
    protected $signature = 'sms:archive
        {--months=12 : Archive records older than this many months}
        {--batch=5000 : Number of records to process per batch}
        {--dry-run : Show what would be archived without moving data}';

    protected $description = 'Archive old SMS log records to archive table (safe, batched, reversible)';

    #[\Override]
    public function handle(): int
    {
        $months = (int) $this->option('months');
        $batchSize = (int) $this->option('batch');
        $dryRun = (bool) $this->option('dry-run');
        $cutoffDate = now()->subMonths($months)->format('Y-m-d H:i:s');

        $this->info("Archiving SMS logs older than {$months} months (before {$cutoffDate})");

        $count = DB::table('sms_logs')
            ->where('created_at', '<', $cutoffDate)
            ->count();

        $this->info("Found {$count} SMS logs eligible for archival.");

        if ($count === 0) {
            $this->info('Nothing to archive.');
            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn('Dry run — no data moved.');
            return self::SUCCESS;
        }

        if (!$this->confirm("Move {$count} SMS logs to archive table?")) {
            $this->info('Cancelled.');
            return self::SUCCESS;
        }

        $totalMoved = 0;

        $this->withProgressBar($count, function ($bar) use ($cutoffDate, $batchSize, &$totalMoved) {
            while (true) {
                $ids = DB::table('sms_logs')
                    ->where('created_at', '<', $cutoffDate)
                    ->orderBy('id')
                    ->limit($batchSize)
                    ->pluck('id')
                    ->toArray();

                if (empty($ids)) {
                    break;
                }

                DB::transaction(function () use ($ids, &$totalMoved) {
                    DB::statement(
                        'INSERT INTO sms_logs_archive SELECT * FROM sms_logs WHERE id IN (' . implode(',', $ids) . ')'
                    );

                    DB::table('sms_logs')
                        ->whereIn('id', $ids)
                        ->delete();

                    $totalMoved += count($ids);
                });

                $bar->advance(count($ids));
            }
        });

        $this->newLine(2);
        $this->info("Archived {$totalMoved} SMS logs.");
        $this->info('Run OPTIMIZE TABLE sms_logs; to reclaim disk space.');

        return self::SUCCESS;
    }
}
