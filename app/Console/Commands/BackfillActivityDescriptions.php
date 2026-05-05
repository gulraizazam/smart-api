<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Helpers\ActivityLogRenderer;
use App\Models\Activity;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Populates `activities.description` for historical rows that pre-date
 * the producer-side description contract. Idempotent: only touches rows
 * where `description IS NULL`, so a crash + rerun resumes cleanly.
 *
 * Run before the make_activity_descriptions_not_null migration in any
 * environment. The migration also runs this same logic, but executing
 * the command first lets you verify output without holding a long ALTER.
 */
final class BackfillActivityDescriptions extends Command
{
    protected $signature = 'activities:backfill-descriptions
                            {--chunk=1000 : Rows per chunk}
                            {--dry-run : Render but do not write}';

    protected $description = 'Populate activities.description for legacy rows';

    public function handle(): int
    {
        $chunk = (int) $this->option('chunk');
        $dryRun = (bool) $this->option('dry-run');

        $remaining = Activity::whereNull('description')->count();
        $this->info("Rows needing backfill: {$remaining}");

        if ($remaining === 0) {
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($remaining);
        $bar->start();

        Activity::whereNull('description')
            ->with(['user', 'serviceR', 'patientR', 'centre'])
            ->orderBy('id')
            ->chunkById($chunk, function ($rows) use ($dryRun, $bar): void {
                $updates = [];
                foreach ($rows as $row) {
                    $updates[(int) $row->id] = ActivityLogRenderer::render($row);
                }

                if (! $dryRun) {
                    self::bulkUpdate($updates);
                }

                $bar->advance(count($updates));
            });

        $bar->finish();
        $this->newLine(2);
        $this->info($dryRun ? 'Dry run complete — no writes performed.' : 'Backfill complete.');

        return self::SUCCESS;
    }

    /**
     * One UPDATE … CASE WHEN per slice — bounded round-trips, parameter
     * binding for safety, and a short transaction per slice so a failure
     * mid-chunk only loses that slice (the next run picks the rest up).
     */
    private static function bulkUpdate(array $updates): void
    {
        foreach (array_chunk($updates, 250, true) as $slice) {
            $ids = array_keys($slice);
            $bindings = [];
            $caseSql = '';

            foreach ($slice as $id => $description) {
                $caseSql .= ' WHEN ? THEN ?';
                $bindings[] = $id;
                $bindings[] = $description;
            }

            $idPlaceholders = implode(',', array_fill(0, count($ids), '?'));
            $bindings = array_merge($bindings, $ids);

            DB::transaction(function () use ($caseSql, $idPlaceholders, $bindings): void {
                DB::update(
                    "UPDATE activities SET description = CASE id{$caseSql} END WHERE id IN ({$idPlaceholders})",
                    $bindings,
                );
            });
        }
    }
}
