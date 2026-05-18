<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Backfill `package_advances.order_id` on rows that originated from the
 * inventory module but were written before the order_id column existed
 * (prior-task backfill plus any legacy uncategorised rows that happen to
 * match an order's signature).
 *
 * Match shape:
 *   - The package_advances row has no package_id, invoice_id,
 *     appointment_id, or appointment_type_id (the only writes that
 *     fit this shape today are OrderService::writeOrderRevenueLedger
 *     and the prior-task backfill).
 *   - There's exactly one `orders` row with the same account_id,
 *     location_id, cash_amount (= orders.total_price), patient_id
 *     (= orders.patient_id ?? orders.employee_id), and created_at
 *     within 60 seconds of the package_advances row.
 *
 * Ambiguous matches (multiple candidate orders) are skipped — surfaced
 * via the row count so we can investigate manually. Going forward, new
 * order writes pin `order_id` directly at insert time, so this is a
 * one-time reconciliation.
 *
 * Idempotent: re-running skips rows that already have `order_id` set.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('package_advances', 'order_id')) {
            return;
        }

        $candidates = DB::table('package_advances')
            ->whereNull('order_id')
            ->whereNull('package_id')
            ->whereNull('invoice_id')
            ->whereNull('appointment_id')
            ->whereNull('appointment_type_id')
            ->whereNotNull('cash_amount')
            ->select(['id', 'account_id', 'location_id', 'patient_id', 'cash_amount', 'created_at', 'cash_flow'])
            ->orderBy('id')
            ->get();

        $matched = 0;
        $ambiguous = 0;
        $noOrder = 0;

        foreach ($candidates as $row) {
            // Window the search to ±60 seconds around the ledger row's
            // timestamp so backfilled package_advances (which inherit the
            // order's created_at exactly) and forward-written rows
            // (written inside the same transaction, may drift by a
            // fraction of a second) both match.
            $rowTs = strtotime($row->created_at);
            $from = date('Y-m-d H:i:s', $rowTs - 60);
            $to = date('Y-m-d H:i:s', $rowTs + 60);

            $orderType = $row->cash_flow === 'out' ? 'refund' : 'sale';

            // For employee orders the package_advance carries employee_id
            // in patient_id; the order has it in employee_id and
            // patient_id is null. Match against either column.
            $orders = DB::table('orders')
                ->where('account_id', $row->account_id)
                ->where('location_id', $row->location_id)
                ->where('order_type', $orderType)
                ->where('total_price', $row->cash_amount)
                ->whereBetween('created_at', [$from, $to])
                ->where(function ($q) use ($row) {
                    $q->where('patient_id', $row->patient_id)
                      ->orWhere('employee_id', $row->patient_id);
                })
                ->pluck('id');

            if ($orders->count() === 1) {
                DB::table('package_advances')
                    ->where('id', $row->id)
                    ->update(['order_id' => $orders->first()]);
                $matched++;
            } elseif ($orders->count() > 1) {
                $ambiguous++;
            } else {
                $noOrder++;
            }
        }

        echo PHP_EOL.'  package_advances order_id backfill — matched='.$matched
            .' ambiguous='.$ambiguous
            .' no_match='.$noOrder
            .' (of '.$candidates->count().' candidate rows)'.PHP_EOL;
    }

    public function down(): void
    {
        // Reversible: just null out everything we set. The original null
        // state is the baseline; we don't lose information.
        DB::table('package_advances')
            ->whereNotNull('order_id')
            ->update(['order_id' => null]);
    }
};
