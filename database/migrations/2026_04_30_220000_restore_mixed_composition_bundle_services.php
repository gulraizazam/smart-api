<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Recover bundle composition for mixed-price bundles that
 * `2026_04_11_100000_fix_bundle_has_services_unique_constraint` couldn't
 * restore.
 *
 * Background:
 *   • `2026_04_08_100024_fix_pivot_table_constraints` deduplicated
 *     `bundle_has_services` via `SELECT DISTINCT *`, collapsing
 *     multi-session bundles down to one row per service.
 *   • `2026_04_11_100000_fix_bundle_has_services_unique_constraint` then
 *     tried to restore the lost slots, but its restoration logic relied
 *     on a clean `total_services / current_count` integer multiplier
 *     AND an exact services_price match. That heuristic only works for
 *     bundles where every service has the same price (e.g.
 *     "CoolGlide - 6 sessions" of one service). For mixed bundles like
 *     "Bundle - Scalp PRGF+" (3× Scalp PRGF+ at 11,995 + 1× Lifestyle
 *     Consultation at 3,000) the price-validation check failed, and the
 *     fallback path silently downgraded `total_services` and
 *     `services_price` on the bundles row instead of restoring rows.
 *
 * This migration uses the surviving `bundle_services_price_history`
 * table — which carries one row per slot per snapshot, in original
 * authoring order — to reconstruct the exact pre-deduplication
 * composition AND its sequence. For each bundle whose current
 * `bundle_has_services` row count or service-id sequence differs
 * from its latest snapshot, we delete + reinsert the rows in
 * snapshot order so the bundle's downstream rendering (in plan
 * dialogs, where children show as `↳` rows) lists services in the
 * same sequence the operator originally configured.
 *
 *   1. Read the latest snapshot rows in chronological insert order.
 *   2. Capture an existing `bundle_has_services` row per service_id
 *      as a template (preserves `discount_type`, `base_service`, etc.).
 *   3. Skip the bundle if the current row set already matches the
 *      snapshot in count AND service-id order.
 *   4. Otherwise: delete all current rows and reinsert one per
 *      snapshot slot, in snapshot order, using the template (or
 *      falling back to services + snapshot data when no template
 *      exists for that service).
 *   5. Recompute `bundles.total_services` and `bundles.services_price`
 *      from the restored row set.
 *
 * Idempotent: a bundle whose `bundle_has_services` already matches
 * the snapshot in both count and sequence is left untouched.
 *
 * Down: irreversible by design — re-deduplicating the rows would
 * destroy the recovered slots.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Find candidate bundles whose latest snapshot disagrees with
        // current state. Two failure modes:
        //   - count mismatch (slots lost to the dedup migration)
        //   - count match but sequence drifted (an earlier patch run
        //     inserted missing slots after the existing ones, leaving
        //     children in the wrong order).
        // Pulling all bundles with snapshot history and filtering in
        // PHP keeps the SQL simple and works on both MariaDB flavours.
        $bundleIds = DB::table('bundle_services_price_history')
            ->select('bundle_id')
            ->groupBy('bundle_id')
            ->pluck('bundle_id')
            ->all();

        if (empty($bundleIds)) {
            return;
        }

        // Each bundle restored in its own transaction so a single bad
        // row (e.g. a snapshot reference to a service that's since
        // been removed in some other way) doesn't block recoveries
        // for unrelated bundles.
        foreach ($bundleIds as $bundleId) {
            try {
                DB::transaction(function () use ($bundleId) {
                    $this->restoreBundle((int) $bundleId);
                });
            } catch (\Throwable $e) {
                \Log::warning('restore_mixed_composition_bundle_services: skipped bundle', [
                    'bundle_id' => $bundleId,
                    'error'     => $e->getMessage(),
                ]);
            }
        }
    }

    private function restoreBundle(int $bundleId): void
    {
        // Skip soft-deleted bundles — no point reshaping a removed row.
        $bundle = DB::table('bundles')->where('id', $bundleId)->whereNull('deleted_at')->first();
        if (! $bundle) {
            return;
        }

        // Latest snapshot: one row per slot, in original authoring order
        // (snapshot history table writes append-only by id).
        $lastSnapTs = DB::table('bundle_services_price_history')
            ->where('bundle_id', $bundleId)
            ->max('created_at');
        if (! $lastSnapTs) {
            return;
        }

        $rawSnapshot = DB::table('bundle_services_price_history')
            ->where('bundle_id', $bundleId)
            ->where('created_at', $lastSnapTs)
            ->orderBy('id')
            ->get(['service_id', 'service_price']);

        if ($rawSnapshot->isEmpty()) {
            return;
        }

        // Snapshot rows can reference services that have since been
        // hard-deleted — the FK on `bundle_has_services.service_id`
        // is `ON DELETE CASCADE`, so any service drop also wiped the
        // pivot row, but the history table retains the dangling
        // reference. Filter those out before reinsert; otherwise the
        // FK rejects the row and the whole bundle restore fails.
        $existingServiceIds = DB::table('services')
            ->whereIn('id', $rawSnapshot->pluck('service_id')->unique()->all())
            ->pluck('id')
            ->map(fn ($v) => (int) $v)
            ->all();
        $existingSet = array_flip($existingServiceIds);

        $snapshotRows = $rawSnapshot->filter(
            fn ($r) => isset($existingSet[(int) $r->service_id])
        )->values();

        if ($snapshotRows->isEmpty()) {
            return;
        }

        $snapshotOrder = $snapshotRows->pluck('service_id')->map(fn ($v) => (int) $v)->all();

        // Current bundle_has_services rows in id (insert) order.
        $currentRows = DB::table('bundle_has_services')
            ->where('bundle_id', $bundleId)
            ->orderBy('id')
            ->get();

        $currentOrder = $currentRows->pluck('service_id')->map(fn ($v) => (int) $v)->all();

        // Already correct? No-op.
        if ($currentOrder === $snapshotOrder) {
            return;
        }

        // Build a per-service template from current rows so we keep any
        // discount_type / base_service / is_category fields that are
        // already populated for live bundles.
        $templates = [];
        foreach ($currentRows as $r) {
            $sid = (int) $r->service_id;
            if (! isset($templates[$sid])) {
                $templates[$sid] = $r;
            }
        }

        // Build the reinsertion batch in snapshot order.
        $batch = [];
        foreach ($snapshotRows as $snapRow) {
            $sid = (int) $snapRow->service_id;
            if (isset($templates[$sid])) {
                $tpl = $templates[$sid];
                $batch[] = [
                    'bundle_id'        => $tpl->bundle_id,
                    'service_id'       => $tpl->service_id,
                    'service_price'    => $tpl->service_price,
                    'calculated_price' => $tpl->calculated_price,
                    'end_node'         => $tpl->end_node,
                    'base_service'     => $tpl->base_service,
                    'get_service'      => $tpl->get_service,
                    'discount_type'    => $tpl->discount_type,
                    'discount_amount'  => $tpl->discount_amount,
                    'is_category'      => $tpl->is_category,
                ];
            } else {
                // Service has no surviving row to mirror — pull
                // service_price from the services catalog and use the
                // snapshot's per-slot allocated price as calculated_price.
                $svcPrice = DB::table('services')->where('id', $sid)->value('price');
                $batch[] = [
                    'bundle_id'        => $bundleId,
                    'service_id'       => $sid,
                    'service_price'    => $svcPrice ?? 0,
                    'calculated_price' => $snapRow->service_price,
                    'end_node'         => 1,
                    'base_service'     => null,
                    'get_service'      => null,
                    'discount_type'    => null,
                    'discount_amount'  => null,
                    'is_category'      => 0,
                ];
            }
        }

        // Replace current rows with the reordered batch.
        DB::table('bundle_has_services')->where('bundle_id', $bundleId)->delete();
        foreach (array_chunk($batch, 100) as $chunk) {
            DB::table('bundle_has_services')->insert($chunk);
        }

        // Recompute totals from the restored row set so the bundles row
        // matches reality.
        $totalServices = count($batch);
        $servicesPrice = 0;
        foreach ($batch as $r) {
            $servicesPrice += (float) $r['service_price'];
        }

        DB::table('bundles')
            ->where('id', $bundleId)
            ->update([
                'total_services' => $totalServices,
                'services_price' => $servicesPrice,
            ]);
    }

    public function down(): void
    {
        // Reversing this migration would re-deduplicate / re-shuffle
        // the rows we just restored, throwing away the recovered slots
        // — an actively destructive operation. Left as a no-op; if a
        // rollback is truly needed, take a DB backup first and run a
        // manual cleanup.
    }
};
