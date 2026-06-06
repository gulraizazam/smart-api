<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\RebuildUpsellCollected;
use App\Services\Upselling\Rollup\UpsellCollectedBuilder;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Rebuilds `upsell_collected_monthly` for every account × the last N months.
 *
 * Scheduled nightly (months=2) + a deeper morning catch-up (months=4) so a
 * late-arriving in-window payment or a refund up to ~90 days back self-heals.
 * `--sync` runs the builder inline (used for the one-off backfill on deploy and
 * in tests); otherwise it dispatches queued jobs. Mirrors
 * RebuildManagementDashboardMetricsCommand.
 */
final class RebuildUpsellCollectedCommand extends Command
{
    protected $signature = 'upsell:rebuild-collected {--months=2} {--account=} {--sync}';

    protected $description = 'Rebuild the upsell_collected_monthly rollup for recent months';

    public function handle(UpsellCollectedBuilder $builder): int
    {
        $months = max(1, (int) $this->option('months'));
        $sync = (bool) $this->option('sync');

        $accountIds = $this->option('account')
            ? [(int) $this->option('account')]
            : DB::table('packages')
                ->whereNotNull('account_id')
                ->distinct()
                ->pluck('account_id')
                ->map(static fn ($v): int => (int) $v)
                ->all();

        // Current month + previous (months - 1).
        $monthDates = [];
        $cursor = Carbon::now()->startOfMonth();
        for ($i = 0; $i < $months; $i++) {
            $monthDates[] = $cursor->copy()->toDateString();
            $cursor->subMonthNoOverflow();
        }

        $count = 0;
        foreach ($accountIds as $accountId) {
            foreach ($monthDates as $monthDate) {
                if ($sync) {
                    $rows = $builder->rebuild($accountId, $monthDate);
                    $this->line("account={$accountId} month={$monthDate} rows={$rows}");
                } else {
                    RebuildUpsellCollected::dispatch($accountId, $monthDate)->afterCommit();
                }
                $count++;
            }
        }

        $this->info($sync ? "Rebuilt {$count} (account × month) slices." : "Dispatched {$count} rebuild jobs.");

        return self::SUCCESS;
    }
}
