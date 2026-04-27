<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Helpers\ACL;
use App\Models\Locations;
use App\Services\Dashboard\Metrics\AtRiskPatientsMetric;
use App\Services\Dashboard\ValueObjects\MetricScope;
use Illuminate\Console\Command;

/**
 * Pre-warm the At-Risk Patients overview cache so every dashboard hit
 * lands on a warm cache (sub-millisecond) instead of paying the
 * ~2-3s cold-load cost.
 *
 * Schedule every 5 minutes from app/Console/Kernel.php to keep the
 * cache permanently primed:
 *   $schedule->command('mgmt-dash:warm-at-risk')->everyFiveMinutes();
 */
class WarmAtRiskOverview extends Command
{
    protected $signature = 'mgmt-dash:warm-at-risk {--account=}';

    protected $description = 'Pre-warm the At-Risk Patients overview + per-branch pool caches.';

    public function handle(AtRiskPatientsMetric $metric): int
    {
        $accountIds = $this->option('account')
            ? [(int) $this->option('account')]
            : Locations::query()
                ->select('account_id')
                ->distinct()
                ->whereNull('deleted_at')
                ->pluck('account_id')
                ->map(static fn ($v): int => (int) $v)
                ->all();

        foreach ($accountIds as $accountId) {
            $start = microtime(true);
            $metric->overview(MetricScope::company($accountId), 25);
            $ms = (microtime(true) - $start) * 1000;
            $this->info(sprintf('account=%d  warmed in %.0f ms', $accountId, $ms));
        }

        return self::SUCCESS;
    }
}
