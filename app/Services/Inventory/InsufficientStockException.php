<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use RuntimeException;

/**
 * Thrown by FifoConsumptionService when a (product, location) does not
 * have enough saleable stock across all open batches to cover the
 * requested quantity.
 *
 * Carries the actual on-hand quantity so the API layer can surface a
 * precise error to the operator ("only 3 available").
 */
class InsufficientStockException extends RuntimeException
{
    public function __construct(
        public readonly int $productId,
        public readonly int $locationId,
        public readonly int $requested,
        public readonly int $available,
        ?string $message = null,
    ) {
        parent::__construct($message ?? sprintf(
            'Insufficient stock for product %d at location %d: requested %d, available %d.',
            $productId,
            $locationId,
            $requested,
            $available,
        ));
    }
}
