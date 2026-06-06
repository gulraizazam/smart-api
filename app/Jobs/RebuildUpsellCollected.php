<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Upselling\Rollup\UpsellCollectedBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

/**
 * Rebuilds one (account, month) slice of `upsell_collected_monthly`.
 * Idempotent via the builder's wipe-open-rows + recreate. WithoutOverlapping
 * keys on "account:month" so the nightly scheduler and any manual rebuild
 * don't race. Mirrors App\Jobs\RebuildDailyMetrics.
 */
final class RebuildUpsellCollected implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public int $timeout = 300;

    public function __construct(
        public readonly int $accountId,
        public readonly string $month, // any date in the target month
    ) {}

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("rebuild-upsell-collected:{$this->accountId}:{$this->month}"))
                ->expireAfter(600)
                ->dontRelease(),
        ];
    }

    public function handle(UpsellCollectedBuilder $builder): void
    {
        $builder->rebuild($this->accountId, $this->month);
    }
}
