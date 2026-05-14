<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Backfills rows into the `migrations` table for migration files whose
 * schema was already applied (typically via SQL restore or schema dump)
 * but whose history row was never recorded. After running,
 * `migrate:status` stops listing those files as Pending and a subsequent
 * `migrate` no longer tries to re-create tables that already exist.
 *
 * Idempotent — only inserts rows for files not already represented.
 * Does NOT delete orphan history rows (entries in the table whose files
 * no longer exist); those need a separate, more cautious decision because
 * removing them changes `migrate:rollback` semantics.
 */
final class RepairMigrationsHistory extends Command
{
    protected $signature = 'migrations:repair-history
                            {--dry-run : Report what would change without writing}
                            {--batch=1 : Batch number to assign to backfilled rows}';

    protected $description = 'Mark already-applied migration files as ran when their history row is missing';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $batch = (int) $this->option('batch');

        $diskFiles = collect(File::glob(database_path('migrations/*.php')))
            ->map(static fn (string $path): string => pathinfo($path, PATHINFO_FILENAME))
            ->values();

        $recorded = DB::table('migrations')->pluck('migration');

        $missing = $diskFiles->diff($recorded)->values();
        $orphans = $recorded->diff($diskFiles)->count();

        $this->info(sprintf(
            'Files on disk: %d  |  Recorded: %d  |  Missing rows: %d  |  Orphan rows: %d',
            $diskFiles->count(),
            $recorded->count(),
            $missing->count(),
            $orphans,
        ));

        if ($missing->isEmpty()) {
            $this->info('Nothing to repair.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn('--dry-run: skipping write. First 5 missing migrations:');
            $missing->take(5)->each(fn (string $name) => $this->line('  '.$name));

            return self::SUCCESS;
        }

        $rows = $missing
            ->map(static fn (string $name): array => ['migration' => $name, 'batch' => $batch])
            ->all();

        $inserted = DB::transaction(static fn (): int => DB::table('migrations')->insert($rows) ? count($rows) : 0);

        $this->info("Inserted {$inserted} migration history rows at batch {$batch}.");

        if ($orphans > 0) {
            $this->warn("Note: {$orphans} orphan rows remain (in table, no file). Not removed.");
        }

        return self::SUCCESS;
    }
}
