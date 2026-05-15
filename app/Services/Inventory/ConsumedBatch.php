<?php

declare(strict_types=1);

namespace App\Services\Inventory;

/**
 * A single batch draw recorded by FifoConsumptionService.
 *
 * Returned to the caller so it can write `order_detail` rows whose
 * `sale_price` matches the batch the units came from, and
 * `order_detail_consumption` rows pinning each draw to its source
 * `stocks.id` (for refund reversal).
 */
final class ConsumedBatch
{
    public function __construct(
        public readonly int $stockId,
        public readonly int $quantity,
        public readonly float $salePrice,
    ) {
    }
}
