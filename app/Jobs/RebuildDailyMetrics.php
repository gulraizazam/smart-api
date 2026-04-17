<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Dashboard\Rollup\DailyMetricsBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

/**
 * Rebuilds one (account, date) slice of dashboard_daily_metrics.
 * Idempotent via updateOrCreate. WithoutOverlapping middleware keys on
 * "account:date" so simultaneous dispatches from the nightly scheduler and
 * the event-driven listener don't race.
 */
final class RebuildDailyMetrics implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public int $timeout = 300;

    public function __construct(
        public readonly int $accountId,
        public readonly string $date,
    ) {}

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("rebuild-daily-metrics:{$this->accountId}:{$this->date}"))
                ->expireAfter(600)
                ->dontRelease(),
        ];
    }

    public function handle(DailyMetricsBuilder $builder): void
    {
        $builder->rebuild($this->accountId, $this->date);
    }
}
