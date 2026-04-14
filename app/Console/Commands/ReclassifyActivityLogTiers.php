<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\UnlocksActivitiesMutation;
use App\Enums\ActivityLogTier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * One-time migration: reclassify every row in `activities` (and
 * `activities_archive`) under the HIPAA-aligned tier vocabulary.
 *
 * Background: today's earlier PR populated `log_tier` with the legacy
 * four-tier vocabulary (clinical/financial/operational/security). The
 * HIPAA-aligned PR collapses everything PHI-touching into `phi_audit`
 * (6-year retention) and auth events into `security_audit`. This command
 * rewrites the existing rows to match.
 *
 * Behavior:
 *   - Chunks through rows with a legacy tier value OR NULL log_tier
 *   - Re-runs ActivityLogTier::classify() using the updated mapping
 *   - Updates in bulk via CASE/WHEN to keep binlog pressure bounded
 *   - Idempotent — running twice is a no-op (already-migrated rows match)
 *
 * Safe to run in production. No data loss; only the `log_tier` column is
 * rewritten. Existing descriptions / activity_type / created_at are
 * untouched.
 */
class ReclassifyActivityLogTiers extends Command
{
    use UnlocksActivitiesMutation;

    protected $signature = 'activities:reclassify-tiers
                            {--chunk=2000 : Rows per chunk}
                            {--dry-run : Report what would change without writing}
                            {--table=both : Which table(s) to process: activities, archive, both}';

    protected $description = 'Reclassify existing rows in activities + activities_archive under HIPAA tier vocabulary.';

    private const LEGACY_VALUES = ['clinical', 'financial', 'operational', 'security'];

    public function handle(): int
    {
        $chunkSize = (int) $this->option('chunk');
        $dryRun = (bool) $this->option('dry-run');
        $whichTable = (string) $this->option('table');

        if ($chunkSize < 1 || $chunkSize > 10_000) {
            $this->error('chunk must be between 1 and 10000');

            return self::INVALID;
        }

        if (! in_array($whichTable, ['activities', 'archive', 'both'], true)) {
            $this->error('--table must be one of: activities, archive, both');

            return self::INVALID;
        }

        $totals = ['activities' => [], 'archive' => []];

        if ($whichTable === 'activities' || $whichTable === 'both') {
            $totals['activities'] = $this->processTable('activities', $chunkSize, $dryRun);
        }

        if ($whichTable === 'archive' || $whichTable === 'both') {
            $totals['archive'] = $this->processTable('activities_archive', $chunkSize, $dryRun);
        }

        $this->renderSummary($totals, $dryRun);

        Log::info('activities.reclassify_tiers.completed', [
            'event' => 'activities.reclassify_tiers.completed',
            'dry_run' => $dryRun,
            'totals' => $totals,
        ]);

        return self::SUCCESS;
    }

    /**
     * @return array{seen: int, updated: int, unclassified_types: int, by_new_tier: array<string, int>}
     */
    private function processTable(string $table, int $chunkSize, bool $dryRun): array
    {
        $seen = 0;
        $updated = 0;
        $unclassified = 0;
        $byNewTier = [];

        $this->line("Processing table: {$table}");

        $lastId = 0;
        while (true) {
            $rows = DB::table($table)
                ->select('id', 'activity_type', 'action', 'log_tier')
                ->where('id', '>', $lastId)
                ->where(function ($q): void {
                    $q->whereIn('log_tier', self::LEGACY_VALUES)
                        ->orWhereNull('log_tier');
                })
                ->orderBy('id')
                ->limit($chunkSize)
                ->get();

            if ($rows->isEmpty()) {
                break;
            }

            $updates = [];
            foreach ($rows as $row) {
                $seen++;
                $lastId = (int) $row->id;

                $tier = ActivityLogTier::classify($row->activity_type, $row->action);
                if ($tier === null) {
                    $unclassified++;

                    continue;
                }

                if ($row->log_tier === $tier->value) {
                    // Already correct — skip write.
                    continue;
                }

                $updates[$tier->value][] = (int) $row->id;
                $byNewTier[$tier->value] = ($byNewTier[$tier->value] ?? 0) + 1;
            }

            if (! $dryRun && $updates !== []) {
                foreach ($updates as $tierValue => $ids) {
                    foreach (array_chunk($ids, 1000) as $slice) {
                        $this->withActivitiesMutationAllowed(function () use ($table, $tierValue, $slice, &$updated): void {
                            DB::transaction(function () use ($table, $tierValue, $slice, &$updated): void {
                                $affected = DB::table($table)
                                    ->whereIn('id', $slice)
                                    ->update(['log_tier' => $tierValue]);
                                $updated += $affected;
                            });
                        });
                    }
                }
            }
        }

        return [
            'seen' => $seen,
            'updated' => $updated,
            'unclassified_types' => $unclassified,
            'by_new_tier' => $byNewTier,
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $totals
     */
    private function renderSummary(array $totals, bool $dryRun): void
    {
        $label = $dryRun ? 'DRY-RUN' : 'DONE';
        $this->info("[{$label}] activity log tier reclassification");

        foreach ($totals as $table => $t) {
            if ($t === []) {
                continue;
            }

            $this->line(sprintf(
                '  %s: seen=%d updated=%d unclassified=%d',
                $table,
                $t['seen'] ?? 0,
                $t['updated'] ?? 0,
                $t['unclassified_types'] ?? 0,
            ));

            if (! empty($t['by_new_tier'])) {
                foreach ($t['by_new_tier'] as $tierValue => $count) {
                    $this->line(sprintf('    %-30s %d', $tierValue, $count));
                }
            }
        }
    }
}
