<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\RebuildDailyMetrics;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Rebuilds dashboard_daily_metrics for every active account × the last N days.
 * Scheduled: nightly (days=1) + morning catch-up (days=3).
 */
final class RebuildManagementDashboardMetricsCommand extends Command
{
    protected $signature = 'management-dashboard:rebuild-metrics {--days=1} {--account=}';

    protected $description = 'Rebuild pre-aggregated daily metrics for the Management Dashboard';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $accountIds = $this->option('account')
            ? [(int) $this->option('account')]
            : DB::table('accounts')
                ->where('suspended', '0')
                ->whereNull('deleted_at')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

        $dates = [];
        for ($i = 1; $i <= $days; $i++) {
            $dates[] = Carbon::yesterday()->subDays($i - 1)->format('Y-m-d');
        }

        $dispatched = 0;
        foreach ($accountIds as $accountId) {
            foreach ($dates as $date) {
                RebuildDailyMetrics::dispatch($accountId, $date)->afterCommit();
                $dispatched++;
            }
        }

        $this->info("Dispatched {$dispatched} rebuild jobs.");

        return self::SUCCESS;
    }
}
