<?php

declare(strict_types=1);

namespace App\Console\Commands\CashFlow;

use App\Models\CashFlow\ExpenseAttachment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Sweep orphaned `expense_attachments` rows.
 *
 * An "orphan" is a row whose `expense_id` stayed NULL past the TTL —
 * the SPA uploaded a file but the user abandoned the form before the
 * expense was saved. Slice-1 default is 48 h; bump via `--ttl=N`.
 *
 * Idempotent. Safe with dedup: a row's R2 blob is only deleted when
 * NO other live (non-soft-deleted) row references the same SHA-256.
 * Otherwise we force-delete the DB row but leave the shared blob
 * alone — this is also why we don't lean on R2CleanupObserver for the
 * blob removal here. The observer fires on every force-delete; we
 * want a conditional drop.
 */
class PruneOrphanedAttachments extends Command
{
    protected $signature = 'cashflow:prune-orphan-attachments
                            {--ttl=48 : Hours an orphan can sit before pruning}
                            {--dry-run : Report what would change without writing}';

    protected $description = 'Hard-delete cashflow attachment rows that stayed unbound past the TTL (default 48h). R2 blobs are kept when still referenced by another row.';

    public function handle(): int
    {
        $ttl = (int) $this->option('ttl');
        $dryRun = (bool) $this->option('dry-run');

        if ($ttl < 1 || $ttl > 720) {
            $this->error('--ttl must be between 1 and 720 hours.');

            return self::INVALID;
        }

        $orphans = ExpenseAttachment::orphaned($ttl)->get();
        $count = $orphans->count();

        if ($count === 0) {
            $this->info('No orphan attachments to prune.');

            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[dry-run] ' : '')."Found {$count} orphan attachment(s) older than {$ttl}h.");

        $blobsDeleted = 0;
        $blobsKept = 0;
        $rowsDeleted = 0;

        foreach ($orphans as $row) {
            $stillReferenced = ExpenseAttachment::where('sha256', $row->sha256)
                ->where('id', '!=', $row->id)
                ->exists();

            $this->line(sprintf(
                '  - id=%d sha=%s… file=%s  → blob %s, row %s',
                $row->id,
                substr((string) $row->sha256, 0, 8),
                $row->file_name,
                $stillReferenced ? 'KEEP (referenced)' : 'DELETE',
                $dryRun ? 'WOULD DELETE' : 'DELETE',
            ));

            if ($dryRun) {
                continue;
            }

            if (! $stillReferenced) {
                try {
                    Storage::disk('r2_invoices')->delete((string) $row->file_path);
                    $blobsDeleted++;
                } catch (\Throwable $e) {
                    $this->warn("    R2 delete failed for {$row->file_path}: {$e->getMessage()}");
                }
            } else {
                $blobsKept++;
            }

            $row->forceDelete();
            $rowsDeleted++;
        }

        if ($dryRun) {
            $this->warn('Dry-run only — no changes made. Re-run without --dry-run to apply.');
        } else {
            $this->info("Removed {$rowsDeleted} rows; deleted {$blobsDeleted} R2 blobs; kept {$blobsKept} blobs (still referenced).");
        }

        return self::SUCCESS;
    }
}
