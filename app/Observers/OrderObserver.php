<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Order;
use App\Models\PackageAdvances;

/**
 * Cascades order lifecycle events to the cash-flow revenue ledger.
 *
 * Order creation already credits the pool via OrderService::createOrder
 * which writes a `package_advances` row → `PackageAdvanceObserver` →
 * `cash_pools.cached_balance` increments. There's nothing for this
 * observer to do on `created()` — the existing path is correct.
 *
 * What WAS missing: order deletion left the ledger row (and therefore
 * the pool credit) in place, so a deleted order silently inflated the
 * pool forever. This observer's `deleted()` hook closes that loop:
 *
 *   Order::DeleteRecord()
 *     → $order->delete()
 *     → OrderObserver::deleted()
 *     → PackageAdvances::where('order_id', $order->id)->each->delete()
 *     → PackageAdvanceObserver::deleted() (per row)
 *     → reversePoolImpact() → cash_pools.cached_balance decremented
 *
 * Pattern mirrors the existing PackageAdvanceObserver / ExpenseObserver
 * etc. — synchronous, no event class, no queue. Registered in
 * AppServiceProvider::registerObservers().
 */
class OrderObserver
{
    /**
     * Reverse the pool credit when an order is hard-deleted.
     *
     * We delete the linked `package_advances` rows one by one (via
     * Eloquent, not raw DB) so each row's `deleted()` observer fires
     * and decrements the pool. Bulk-deleting via `Builder::delete()`
     * would skip the observer and orphan the pool credit, defeating
     * the whole point of this hook.
     */
    public function deleted(Order $order): void
    {
        PackageAdvances::where('order_id', $order->id)
            ->get()
            ->each(fn (PackageAdvances $row) => $row->delete());
    }
}
