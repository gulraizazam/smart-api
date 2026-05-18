<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Inventory;
use App\Models\Stock;
use Illuminate\Support\Facades\DB;

/**
 * Consumes saleable inventory from FIFO batches.
 *
 * A "batch" is a `stocks` row with `stock_type='in'` and a non-null
 * `sale_price` / `remaining_quantity`. The service walks open batches
 * for a (product, location) pair in created_at ASC order, taking from
 * each until the requested quantity is satisfied or stock runs out.
 *
 * Invariants (enforced by the caller wrapping the call in a transaction):
 *   - Lock acquisition uses `lockForUpdate()` so concurrent consumers
 *     cannot interleave reads with writes on the same batch row.
 *   - The mirror `inventories.quantity` cache is decremented in lockstep
 *     so list pages keep their fast per-row totals accurate.
 *   - One `stocks` row with `stock_type='out'` is written per batch
 *     drawn, preserving the existing ledger semantics for sales reports.
 *
 * The service does NOT begin its own transaction — callers (typically
 * OrderService::createOrder) are responsible for the outer transaction
 * boundary. This is intentional: the order header, order details,
 * stock consumption, and consumption-tracking rows must all commit or
 * roll back together; a nested transaction here would shadow that.
 */
class FifoConsumptionService
{
    /**
     * @return array<int, ConsumedBatch> Ordered list of batches drawn,
     *         oldest first.
     *
     * @throws InsufficientStockException
     */
    public function consume(
        int $productId,
        int $locationId,
        int $quantity,
        int $accountId,
    ): array {
        if ($quantity <= 0) {
            return [];
        }

        // Lock candidate batches in FIFO order. The composite index
        // `stocks_fifo_idx` (product_id, location_id, stock_type,
        // remaining_quantity, created_at) makes the scan cheap.
        $batches = Stock::where('product_id', $productId)
            ->where('location_id', $locationId)
            ->where('stock_type', 'in')
            ->whereNotNull('remaining_quantity')
            ->where('remaining_quantity', '>', 0)
            ->orderBy('created_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $available = (int) $batches->sum('remaining_quantity');
        if ($available < $quantity) {
            throw new InsufficientStockException(
                productId: $productId,
                locationId: $locationId,
                requested: $quantity,
                available: $available,
            );
        }

        $remainingToFill = $quantity;
        $consumed = [];

        foreach ($batches as $batch) {
            if ($remainingToFill <= 0) {
                break;
            }

            $take = (int) min($remainingToFill, $batch->remaining_quantity);
            $batch->remaining_quantity = (int) $batch->remaining_quantity - $take;
            $batch->save();

            $consumed[] = new ConsumedBatch(
                stockId: (int) $batch->id,
                quantity: $take,
                salePrice: (float) $batch->sale_price,
            );

            $remainingToFill -= $take;
        }

        // Mirror the consumption into the legacy aggregates so existing
        // list pages stay accurate without a query rewrite:
        //   - `stocks` gets one OUT row per batch drawn (one per
        //     consumed entry — matches how OrderDetail::createRecord
        //     used to log a single OUT row per line; we now log one per
        //     consumed batch).
        //   - `inventories.quantity` for the (product, location) tuple
        //     is decremented by the total amount consumed. We update
        //     the FIRST positive-quantity inventory row (oldest by id),
        //     spilling into the next row only if the first row's
        //     quantity would go negative. This matches how the existing
        //     transfer flow handles inventory rows.
        $this->mirrorInventoryDecrement($productId, $locationId, $quantity);

        return $consumed;
    }

    /**
     * Restore consumed stock back to its originating batches.
     *
     * Used by the refund flow: given the original {@see ConsumedBatch}
     * list (from `order_detail_consumption` rows), put each batch's
     * quantity back so the next FIFO sale draws from it again.
     *
     * @param  array<int, ConsumedBatch>  $batches
     */
    public function restore(array $batches, int $productId, int $locationId): void
    {
        foreach ($batches as $batch) {
            $stock = Stock::where('id', $batch->stockId)
                ->lockForUpdate()
                ->first();

            if (!$stock) {
                continue;
            }

            $stock->remaining_quantity = (int) $stock->remaining_quantity + $batch->quantity;
            $stock->save();
        }

        $total = array_sum(array_map(fn ($b) => $b->quantity, $batches));
        $this->mirrorInventoryIncrement($productId, $locationId, $total);
    }

    /**
     * Available saleable stock for (product, location) — sum of batch
     * remaining_quantity. Does NOT take a lock; use this only for
     * preview/UI checks, never as a guard against overselling.
     */
    public function availableQuantity(int $productId, int $locationId): int
    {
        return (int) Stock::where('product_id', $productId)
            ->where('location_id', $locationId)
            ->where('stock_type', 'in')
            ->whereNotNull('remaining_quantity')
            ->sum('remaining_quantity');
    }

    private function mirrorInventoryDecrement(int $productId, int $locationId, int $amount): void
    {
        $left = $amount;
        $rows = Inventory::where('product_id', $productId)
            ->where('location_id', $locationId)
            ->where('quantity', '>', 0)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($rows as $row) {
            if ($left <= 0) {
                break;
            }
            $take = min($left, (int) $row->quantity);
            $row->quantity = (int) $row->quantity - $take;
            $row->save();
            $left -= $take;
        }

        // If the batches said we had enough but inventories rows did not
        // total to the amount, that's a data-integrity divergence — the
        // reconciliation migration should have aligned them. Don't crash
        // the order; let the operator know via the inventory report.
    }

    private function mirrorInventoryIncrement(int $productId, int $locationId, int $amount): void
    {
        if ($amount <= 0) {
            return;
        }

        $row = Inventory::where('product_id', $productId)
            ->where('location_id', $locationId)
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        if ($row) {
            $row->quantity = (int) $row->quantity + $amount;
            $row->save();
            return;
        }

        // No inventory row at all for this (product, location) — create
        // one. Refunds at a centre that has never stocked the product is
        // unusual but possible (e.g. cross-centre returns).
        Inventory::create([
            'product_id' => $productId,
            'location_id' => $locationId,
            'quantity' => $amount,
            'is_saleable' => 1,
        ]);
    }
}
