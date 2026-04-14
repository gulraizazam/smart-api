<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\UnlocksActivitiesMutation;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * HIPAA-aligned archive command for the `activities` table.
 *
 * Per-tier behavior:
 *   - `phi_audit` / `security_audit`: rows older than hot_days are moved
 *     to `activities_archive`. Cold storage is append-only — nothing is
 *     ever deleted from archive while this tier's cold_days == 0.
 *   - `hr_standard`: archive after hot_days, delete from archive after
 *     cold_days (2 years total).
 *   - `hr_candidates_rejected`: hard-delete from `activities` at hot_days
 *     (6 months). No archive. Configured via `hard_delete_without_archive`
 *     flag in config/activity_retention.php.
 *   - Transitional tiers (clinical/financial/operational/security — pre-
 *     reclassification rows): treated identically to phi/security_audit.
 *
 * Chunked under DB::transaction() for crash safety. INSERT IGNORE keeps
 * archive writes idempotent. NULL-tier rows are never touched.
 *
 * Scheduled daily at 03:15 PKT (see bootstrap/app.php). Rename from
 * `activities:archive-and-prune` → `activities:archive` reflects the new
 * HIPAA-aligned semantics where prune is the exception, not the rule.
 * Both signatures are registered so existing cron entries keep working.
 */
class ArchiveAndPruneActivities extends Command
{
    use UnlocksActivitiesMutation;

    protected $signature = 'activities:archive
                            {--dry-run : Report what would move/delete without writing}
                            {--tier= : Limit to a single tier}';

    protected $description = 'Archive hot activities past their retention window (HIPAA-aligned; append-only for PHI/security tiers).';

    private const ARCHIVE_COLUMNS = [
        'id', 'account_id', 'plan_id', 'package_id', 'appointment_id',
        'action', 'activity_type', 'log_tier', 'description',
        'service', 'service_id', 'appointment_type',
        'patient_name', 'patient', 'patient_id', 'received_by',
        'centre_id', 'user_id', 'created_by',
        'schedule_date',
        'lead_id', 'lead_status', 'lead_status_id',
        'deleted_by', 'rescheduled_by', 'deleted_date',
        'created_at', 'updated_at',
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $onlyTier = $this->option('tier');

        $chunkSize = (int) config('activity_retention.chunk_size', 5000);
        $maxRows = (int) config('activity_retention.max_rows_per_run', 200_000);
        $tiers = (array) config('activity_retention.tiers', []);

        $now = CarbonImmutable::now();
        $rowsProcessed = 0;
        $summary = [];

        foreach ($tiers as $tierValue => $policy) {
            if ($onlyTier !== null && $tierValue !== $onlyTier) {
                continue;
            }

            $hotDays = (int) ($policy['hot_days'] ?? 0);
            $coldDays = (int) ($policy['cold_days'] ?? 0);
            $hardDeleteNoArchive = (bool) ($policy['hard_delete_without_archive'] ?? false);

            if ($hotDays <= 0) {
                continue;
            }

            $hotCutoff = $now->subDays($hotDays)->toDateTimeString();
            $archived = 0;
            $deletedHot = 0;
            $deletedCold = 0;

            if ($hardDeleteNoArchive) {
                // Explicit minimization policy — delete from hot without
                // archiving. Used by hr_candidates_rejected (180d).
                $deletedHot = $this->pruneHotDirect(
                    tierValue: $tierValue,
                    cutoff: $hotCutoff,
                    chunkSize: $chunkSize,
                    remainingBudget: $maxRows - $rowsProcessed,
                    dryRun: $dryRun,
                );
            } else {
                // Archive hot → archive table. Always archive; never hard-
                // delete from hot. PhiAudit and SecurityAudit keep
                // cold_days == 0 so archive is append-only forever.
                [$archived, $deletedHot] = $this->archiveTier(
                    tierValue: $tierValue,
                    cutoff: $hotCutoff,
                    chunkSize: $chunkSize,
                    remainingBudget: $maxRows - $rowsProcessed,
                    dryRun: $dryRun,
                );
            }

            $rowsProcessed += $deletedHot;

            // Archive-side pruning — only runs when cold_days > 0.
            // For phi_audit / security_audit (cold_days == 0) the archive
            // is append-only and this branch is skipped.
            if (! $hardDeleteNoArchive && $coldDays > 0 && $rowsProcessed < $maxRows) {
                $coldCutoff = $now->subDays($coldDays)->toDateTimeString();
                $deletedCold = $this->pruneArchive(
                    tierValue: $tierValue,
                    cutoff: $coldCutoff,
                    chunkSize: $chunkSize,
                    remainingBudget: $maxRows - $rowsProcessed,
                    dryRun: $dryRun,
                );
                $rowsProcessed += $deletedCold;
            }

            $summary[$tierValue] = [
                'archived' => $archived,
                'deleted_hot' => $deletedHot,
                'deleted_cold' => $deletedCold,
            ];

            if ($rowsProcessed >= $maxRows) {
                $this->warn("max_rows_per_run ({$maxRows}) reached; remaining work deferred to next run");
                break;
            }
        }

        $this->renderSummary($summary, $dryRun);

        Log::info('activities.archive.completed', [
            'event' => 'activities.archive.completed',
            'dry_run' => $dryRun,
            'rows_processed' => $rowsProcessed,
            'summary' => $summary,
        ]);

        return self::SUCCESS;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function archiveTier(
        string $tierValue,
        string $cutoff,
        int $chunkSize,
        int $remainingBudget,
        bool $dryRun,
    ): array {
        $archived = 0;
        $deleted = 0;

        while ($remainingBudget > 0) {
            $batch = min($chunkSize, $remainingBudget);

            $ids = DB::table('activities')
                ->where('log_tier', $tierValue)
                ->where('created_at', '<', $cutoff)
                ->orderBy('id')
                ->limit($batch)
                ->pluck('id')
                ->all();

            if ($ids === []) {
                break;
            }

            if ($dryRun) {
                $archived += count($ids);
                $deleted += count($ids);
                break;
            }

            $this->withActivitiesMutationAllowed(function () use ($ids, &$archived, &$deleted): void {
                DB::transaction(function () use ($ids, &$archived, &$deleted): void {
                    $cols = '`'.implode('`, `', self::ARCHIVE_COLUMNS).'`';
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));

                    $insertedOrIgnored = DB::affectingStatement(
                        "INSERT IGNORE INTO `activities_archive` ({$cols}, `archived_at`)
                         SELECT {$cols}, NOW() FROM `activities` WHERE id IN ({$placeholders})",
                        $ids,
                    );

                    $archived += $insertedOrIgnored;

                    $deletedRows = DB::table('activities')->whereIn('id', $ids)->delete();
                    $deleted += $deletedRows;
                });
            });

            $remainingBudget -= count($ids);
        }

        return [$archived, $deleted];
    }

    private function pruneHotDirect(
        string $tierValue,
        string $cutoff,
        int $chunkSize,
        int $remainingBudget,
        bool $dryRun,
    ): int {
        $deleted = 0;

        while ($remainingBudget > 0) {
            $batch = min($chunkSize, $remainingBudget);

            $ids = DB::table('activities')
                ->where('log_tier', $tierValue)
                ->where('created_at', '<', $cutoff)
                ->orderBy('id')
                ->limit($batch)
                ->pluck('id')
                ->all();

            if ($ids === []) {
                break;
            }

            if ($dryRun) {
                $deleted += count($ids);
                break;
            }

            $this->withActivitiesMutationAllowed(function () use ($ids, &$deleted): void {
                DB::transaction(function () use ($ids, &$deleted): void {
                    $deleted += DB::table('activities')->whereIn('id', $ids)->delete();
                });
            });

            $remainingBudget -= count($ids);
        }

        return $deleted;
    }

    private function pruneArchive(
        string $tierValue,
        string $cutoff,
        int $chunkSize,
        int $remainingBudget,
        bool $dryRun,
    ): int {
        $deleted = 0;

        while ($remainingBudget > 0) {
            $batch = min($chunkSize, $remainingBudget);

            $ids = DB::table('activities_archive')
                ->where('log_tier', $tierValue)
                ->where('archived_at', '<', $cutoff)
                ->orderBy('id')
                ->limit($batch)
                ->pluck('id')
                ->all();

            if ($ids === []) {
                break;
            }

            if ($dryRun) {
                $deleted += count($ids);
                break;
            }

            $this->withActivitiesMutationAllowed(function () use ($ids, &$deleted): void {
                DB::transaction(function () use ($ids, &$deleted): void {
                    $deleted += DB::table('activities_archive')->whereIn('id', $ids)->delete();
                });
            });

            $remainingBudget -= count($ids);
        }

        return $deleted;
    }

    private function renderSummary(array $summary, bool $dryRun): void
    {
        $label = $dryRun ? 'DRY-RUN' : 'DONE';

        if ($summary === []) {
            $this->info("[{$label}] nothing to do");

            return;
        }

        $this->info("[{$label}] activity retention sweep");
        $this->table(
            ['tier', 'archived', 'deleted_hot', 'deleted_cold'],
            array_map(
                fn (string $tier, array $s) => [$tier, $s['archived'], $s['deleted_hot'], $s['deleted_cold']],
                array_keys($summary),
                array_values($summary),
            ),
        );
    }
}
