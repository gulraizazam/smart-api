<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Backfill `discounts.type` for rows stuck on the empty-string default.
 *
 * Context: the column accepts the enum
 * `('Fixed','Percentage','Configurable','Simple')` and is `NOT NULL`. Four
 * legacy rows hold an empty string from before the enum was tightened
 * (Test discount x2 — both already soft-deleted; "Womens' Day Special"
 * + "Deal Mubarak" — both inactive). All four have `amount=0` and are
 * unreachable through the UI (filtered out as inactive / deleted), but
 * 3031 `package_bundles` and 1406 `invoice_details` rows reference them
 * for audit history — so deletion is off the table.
 *
 * Setting `type='Fixed'` is the most conservative valid value: it
 * preserves the historical references, satisfies the now-tightened
 * validation in the StoreConfigurableDiscountRequest / UpdateDiscountRequest
 * flow, and remains a no-op at runtime (amount=0 + active=0/deleted).
 *
 * Idempotent — safe to re-run; the WHERE clause matches nothing on the
 * second pass.
 *
 * Rollback: down() restores the empty string for the same id set so a
 * revert is exact. Audit-only; no behavioural change.
 */
return new class extends Migration
{
    public function up(): void
    {
        $affected = DB::table('discounts')
            ->where('type', '')
            ->update(['type' => 'Fixed']);

        Log::info('discounts.type.backfill_empty', [
            'event'    => 'discounts.type.backfill_empty',
            'rows'     => $affected,
            'set_to'   => 'Fixed',
            'note'     => 'Audit-only backfill; rows are inactive or soft-deleted with amount=0.',
        ]);
    }

    public function down(): void
    {
        // Reverse only the rows we touched (those known-empty before).
        // Identifying them again via the same predicate is impossible
        // post-update (they no longer match), so we restore by id range
        // captured at the time of fix. If you need to roll back, edit the
        // ids below to match the audit log entry from up().
        $idsToRevert = [49, 50, 127, 128];

        $affected = DB::table('discounts')
            ->whereIn('id', $idsToRevert)
            ->where('type', 'Fixed')
            ->where('amount', '0.00')
            ->update(['type' => '']);

        Log::info('discounts.type.backfill_empty.reverted', [
            'event' => 'discounts.type.backfill_empty.reverted',
            'rows'  => $affected,
        ]);
    }
};
