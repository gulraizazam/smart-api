<?php

declare(strict_types=1);

namespace App\Console\Commands\CashFlow;

use App\Enums\ExpenseStatus;
use App\Models\CashFlow\Expense;
use App\Services\CashFlow\ExpenseService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Backfill the post-2026-05-13 auto-flag rules onto every existing
 * payment.
 *
 * Why this exists:
 *   1. Several rules were retired (backdated / self-approved / daily-
 *      splitting / perfect-match-advance). Rows still carry their old
 *      `flag_reason` text until something touches them.
 *   2. Remaining rules now emit short keyword labels ("No receipt",
 *      "Round amount", …). Old rows still carry full-sentence labels.
 *   3. Terminal states (approved / rejected / voided) clear the flag.
 *      Pre-existing rows in those states were never re-evaluated.
 *   4. New rules (description-duplicate, voided-twin, round-amount,
 *      reused-receipt) only ran on fresh creates after the change.
 *
 * Idempotent, dry-run safe, chunked. Re-runnable any time.
 */
class RebuildExpenseFlags extends Command
{
    protected $signature = 'cashflow:rebuild-flags
                            {--account= : Limit to a single account_id (default = all accounts)}
                            {--chunk=200 : Rows per chunk when re-evaluating pending rows}
                            {--dry-run : Report what would change without writing}';

    protected $description = 'Re-evaluate auto-flag rules against every existing payment so retired rules drop, new rules apply, and labels match the current short-form vocabulary.';

    public function handle(ExpenseService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunkSize = (int) $this->option('chunk');
        $accountFilter = $this->option('account');

        if ($chunkSize < 1 || $chunkSize > 5_000) {
            $this->error('chunk must be between 1 and 5000');

            return self::INVALID;
        }

        $accountId = $accountFilter !== null ? (int) $accountFilter : null;

        // ─── Step 1: clear flags on voided rows ──────────────────────
        // Void is the only true terminal state under Option A (Pending /
        // Rejected workflow retired 2026-05-13). A voided row should not
        // carry an active flag — the void *is* the resolution.
        $terminalQuery = Expense::query()
            ->whereNotNull('voided_at')
            ->where(function ($q) {
                $q->where('is_flagged', true)->orWhereNotNull('flag_reason');
            });
        if ($accountId !== null) {
            $terminalQuery->where('account_id', $accountId);
        }

        $terminalCount = (clone $terminalQuery)->count();
        if (!$dryRun && $terminalCount > 0) {
            (clone $terminalQuery)->update([
                'is_flagged' => false,
                'flag_reason' => null,
            ]);
        }
        $this->info(($dryRun ? '[dry-run] ' : '') . "Cleared flag state on {$terminalCount} voided rows.");

        // ─── Step 2: re-evaluate every live row ──────────────────────
        // Every live (non-voided) row in the Option-A world is Approved
        // status — there's no Pending / Rejected distinction anymore.
        // Re-run the rule set so retired rules drop off, new rules
        // (Above threshold, etc.) apply, and reason strings match the
        // current short-form vocabulary.
        $liveQuery = Expense::query()->whereNull('voided_at');
        if ($accountId !== null) {
            $liveQuery->where('account_id', $accountId);
        }

        $liveTotal = (clone $liveQuery)->count();
        $reevaluated = 0;
        $stillFlagged = 0;
        $errors = 0;

        if ($liveTotal === 0) {
            $this->info('No live rows to re-evaluate.');
        } else {
            $bar = $this->output->createProgressBar($liveTotal);
            $bar->start();

            $liveQuery
                ->with(['paymentMethod', 'category'])
                ->chunkById($chunkSize, function ($rows) use ($service, $dryRun, &$reevaluated, &$stillFlagged, &$errors, $bar) {
                    foreach ($rows as $expense) {
                        try {
                            if ($dryRun) {
                                $reevaluated++;
                                $bar->advance();
                                continue;
                            }
                            $reasons = $service->rebuildFlagsForRow($expense);
                            $reevaluated++;
                            if (!empty($reasons)) {
                                $stillFlagged++;
                            }
                        } catch (\Throwable $e) {
                            $errors++;
                            Log::error('cashflow:rebuild-flags row failed', [
                                'expense_id' => $expense->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                        $bar->advance();
                    }
                });

            $bar->finish();
            $this->newLine();
            $this->info(($dryRun ? '[dry-run] ' : '') . "Re-evaluated {$reevaluated} live rows ({$stillFlagged} still flag, {$errors} errored).");
        }

        if ($dryRun) {
            $this->warn('Dry-run only — no rows were modified. Re-run without --dry-run to apply.');
        }

        return self::SUCCESS;
    }
}
