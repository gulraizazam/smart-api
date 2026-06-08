<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\UnlocksActivitiesMutation;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Export-then-delete retention for flat, date-keyed log tables (sms_logs,
 * activities). For each configured table it streams the rows older than the
 * retention window into a gzipped CSV file — the keep-copy — and only then
 * deletes them from the live table, in chunks.
 *
 * Why export-to-file (not an in-DB *_archive table): the archive tables
 * double the data inside the same database and don't reclaim space; a file is
 * a true off-table copy. See project_prod_log_retention_dormant.
 *
 * `authorized` tables route the DELETE through the tamper-evident mutation
 * gate (UnlocksActivitiesMutation) so the removal is *logged* by the audit
 * triggers rather than blocked — see project_db_change_audit_spy.
 *
 * Runs one-time for the backlog and monthly via the scheduler (bootstrap/app.php,
 * with --force so the confirmation is skipped unattended).
 */
class PruneLogTables extends Command
{
    use UnlocksActivitiesMutation;

    protected $signature = 'logs:prune
                            {--months= : Retention window in months (default: config log_retention.months)}
                            {--table= : Limit to a single configured table}
                            {--dry-run : Report eligible counts without exporting or deleting}
                            {--force : Skip the per-table confirmation (for scheduled/unattended runs)}';

    protected $description = 'Export log rows older than the retention window to a compressed file, then delete them from the live table.';

    public function handle(): int
    {
        $months = $this->option('months') !== null
            ? (int) $this->option('months')
            : (int) config('log_retention.months', 6);

        if ($months < 1) {
            $this->error('--months must be >= 1');

            return self::INVALID;
        }

        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $only = $this->option('table');
        $chunk = max(1, (int) config('log_retention.chunk', 2000));
        $maxRows = (int) config('log_retention.max_rows_per_run', 0); // 0 = unbounded
        $exportDir = (string) config('log_retention.export_path', storage_path('app/log-archive'));
        $tables = (array) config('log_retention.tables', []);

        if ($only !== null && ! array_key_exists($only, $tables)) {
            $this->error("Unknown table '{$only}'. Configured: ".implode(', ', array_keys($tables)));

            return self::INVALID;
        }

        $cutoff = CarbonImmutable::now()->subMonths($months)->toDateTimeString();
        $this->info("Retention: keep last {$months} months — removing rows created before {$cutoff}.");

        $budget = $maxRows > 0 ? $maxRows : PHP_INT_MAX;
        $grandDeleted = 0;

        foreach ($tables as $table => $cfg) {
            if ($only !== null && $only !== $table) {
                continue;
            }

            $dateCol = (string) ($cfg['date'] ?? 'created_at');
            $authorized = (bool) ($cfg['authorized'] ?? false);

            $eligible = DB::table($table)->where($dateCol, '<', $cutoff)->count();
            if ($eligible === 0) {
                $this->line("  {$table}: nothing older than the cutoff.");

                continue;
            }
            $this->info("  {$table}: {$eligible} row(s) older than {$months} months.");

            if ($dryRun) {
                $this->warn('    dry-run — not exporting or deleting.');

                continue;
            }

            if (! $force && ! $this->confirm("    Export + delete {$eligible} row(s) from {$table}?", false)) {
                $this->line('    skipped.');

                continue;
            }

            if ($budget <= 0) {
                $this->warn("    max_rows_per_run reached — {$table} deferred to next run.");

                continue;
            }

            $exportPath = $this->exportOldRows($table, $dateCol, $cutoff, $chunk, $exportDir);
            $this->info("    exported keep-copy -> {$exportPath}");

            $deleted = $this->deleteOldRows($table, $dateCol, $cutoff, $chunk, $authorized, $budget);
            $budget -= $deleted;
            $grandDeleted += $deleted;
            $this->info("    deleted {$deleted} row(s) from {$table}.");

            Log::info('logs.prune.table', [
                'event' => 'logs.prune.table',
                'table' => $table,
                'cutoff' => $cutoff,
                'eligible' => $eligible,
                'deleted' => $deleted,
                'export' => $exportPath,
            ]);
        }

        $this->info("Done. Deleted {$grandDeleted} row(s) total this run.");

        return self::SUCCESS;
    }

    /**
     * Stream the eligible rows to a gzipped CSV (header row + '\N' for NULLs so
     * it round-trips via LOAD DATA INFILE). Chunked — memory-safe on
     * multi-million-row tables. Read-only.
     */
    private function exportOldRows(string $table, string $dateCol, string $cutoff, int $chunk, string $dir): string
    {
        File::ensureDirectoryExists($dir);
        $stamp = CarbonImmutable::now()->format('Ymd_His');
        $path = rtrim($dir, '/')."/{$table}_before_".substr($cutoff, 0, 10)."_{$stamp}.csv.gz";

        $gz = gzopen($path, 'wb9');
        if ($gz === false) {
            throw new \RuntimeException("Could not open export file for writing: {$path}");
        }

        try {
            $columns = Schema::getColumnListing($table);
            gzwrite($gz, $this->toCsvLine($columns));

            DB::table($table)
                ->where($dateCol, '<', $cutoff)
                ->chunkById($chunk, function ($rows) use ($gz, $columns): void {
                    foreach ($rows as $row) {
                        $values = array_map(static fn (string $c) => $row->{$c} ?? null, $columns);
                        gzwrite($gz, $this->toCsvLine($values));
                    }
                });
        } finally {
            gzclose($gz);
        }

        return $path;
    }

    /**
     * Batched delete of the same eligible set, bounded by the per-run budget.
     * Authorized tables route through the tamper-evident gate.
     */
    private function deleteOldRows(string $table, string $dateCol, string $cutoff, int $chunk, bool $authorized, int $budget): int
    {
        $deleted = 0;

        while (true) {
            $batch = (int) min($chunk, $budget - $deleted);
            if ($batch <= 0) {
                break;
            }

            $run = fn (): int => DB::table($table)
                ->where($dateCol, '<', $cutoff)
                ->orderBy('id')
                ->limit($batch)
                ->delete();

            $n = $authorized
                ? (int) $this->withActivitiesMutationAllowed($run)
                : $run();

            $deleted += $n;

            if ($n < $batch) {
                break; // table drained
            }
        }

        return $deleted;
    }

    /**
     * One CSV line, MySQL-friendly NULL marker, escape param passed explicitly
     * (PHP 8.4 deprecates relying on its default).
     */
    private function toCsvLine(array $fields): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv(
            $handle,
            array_map(static fn ($v) => $v === null ? '\\N' : (string) $v, $fields),
            ',',
            '"',
            '\\',
            "\n",
        );
        rewind($handle);
        $line = (string) stream_get_contents($handle);
        fclose($handle);

        return $line;
    }
}
