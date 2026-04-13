<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fix bundle_has_services data loss caused by the deduplication in
 * 2026_04_08_100024_fix_pivot_table_constraints.
 *
 * That migration ran SELECT DISTINCT * which collapsed multi-session
 * bundle rows (e.g. "CoolGlide - 6 sessions" had 6 identical rows
 * for the same service) into a single row.
 *
 * This migration:
 *   1. Drops the unique constraint on (bundle_id, service_id) if it exists
 *   2. Restores collapsed rows using the stored total_services count
 *   3. Fixes total_services for bundles that can't be cleanly restored
 */
return new class extends Migration
{
    private function indexExists(string $table, string $index): bool
    {
        $rows = DB::select(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1',
            [$table, $index]
        );
        return ! empty($rows);
    }

    public function up(): void
    {
        // ── 1. Drop unique constraint if it exists ──
        if ($this->indexExists('bundle_has_services', 'uq_bundle_service')) {
            // FK on bundle_id / service_id may depend on the unique index — give them
            // plain single-column indexes to fall back on before we drop the unique.
            if (! $this->indexExists('bundle_has_services', 'idx_bhs_bundle_id')) {
                DB::statement('ALTER TABLE bundle_has_services ADD INDEX idx_bhs_bundle_id (bundle_id)');
            }
            if (! $this->indexExists('bundle_has_services', 'idx_bhs_service_id')) {
                DB::statement('ALTER TABLE bundle_has_services ADD INDEX idx_bhs_service_id (service_id)');
            }

            try {
                DB::statement('DROP INDEX uq_bundle_service ON bundle_has_services');
            } catch (\Throwable $e) {
                \Log::warning('Could not drop uq_bundle_service: ' . $e->getMessage());
            }
        }

        // ── 1b. Fix id column: ensure it is AUTO_INCREMENT PRIMARY KEY ──
        $columns = DB::select("SHOW COLUMNS FROM bundle_has_services WHERE Field = 'id'");
        if (! empty($columns) && stripos($columns[0]->Extra, 'auto_increment') === false) {
            DB::statement('ALTER TABLE bundle_has_services MODIFY id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (id)');
        }

        // ── 2. Restore collapsed multi-session rows ──
        $bundles = DB::table('bundles')
            ->where('type', '!=', 'single')
            ->get(['id', 'total_services', 'services_price']);

        foreach ($bundles as $bundle) {
            $rows = DB::table('bundle_has_services')
                ->where('bundle_id', $bundle->id)
                ->get();

            $actualCount = $rows->count();

            if ($actualCount === 0 || $bundle->total_services <= $actualCount) {
                continue;
            }

            $ratio = $bundle->total_services / $actualCount;

            // Only restore if the ratio is a clean integer and
            // the calculated services_price validates
            if ($ratio != intval($ratio)) {
                DB::table('bundles')
                    ->where('id', $bundle->id)
                    ->update([
                        'total_services' => $actualCount,
                        'services_price' => $rows->sum('service_price'),
                    ]);
                continue;
            }

            $multiplier = intval($ratio);
            $expectedPrice = $rows->sum('service_price') * $multiplier;

            if (abs($expectedPrice - (float) $bundle->services_price) > 0.01) {
                DB::table('bundles')
                    ->where('id', $bundle->id)
                    ->update([
                        'total_services' => $actualCount,
                        'services_price' => $rows->sum('service_price'),
                    ]);
                continue;
            }

            // Clean ratio + price validates: duplicate each existing row
            $duplicates = [];
            foreach ($rows as $row) {
                $insert = [
                    'bundle_id'        => $row->bundle_id,
                    'service_id'       => $row->service_id,
                    'service_price'    => $row->service_price,
                    'calculated_price' => $row->calculated_price,
                    'end_node'         => $row->end_node,
                ];

                // Include extra columns if they exist on the row
                foreach (['base_service', 'get_service', 'discount_type', 'discount_amount', 'is_category'] as $col) {
                    if (property_exists($row, $col)) {
                        $insert[$col] = $row->$col;
                    }
                }

                for ($i = 1; $i < $multiplier; $i++) {
                    $duplicates[] = $insert;
                }
            }

            if (! empty($duplicates)) {
                foreach (array_chunk($duplicates, 100) as $chunk) {
                    DB::table('bundle_has_services')->insert($chunk);
                }
            }
        }
    }

    public function down(): void
    {
        // Reverse: deduplicate rows and restore unique constraint
        // NOTE: this will destroy multi-session data again
        $bundles = DB::table('bundles')
            ->where('type', '!=', 'single')
            ->get(['id']);

        foreach ($bundles as $bundle) {
            $rows = DB::table('bundle_has_services')
                ->where('bundle_id', $bundle->id)
                ->get();

            // Group by service_id, keep only the first row per service
            $seen = [];
            $deleteIds = [];
            foreach ($rows as $row) {
                if (isset($seen[$row->service_id])) {
                    $deleteIds[] = $row->id;
                } else {
                    $seen[$row->service_id] = true;
                }
            }

            if (! empty($deleteIds)) {
                DB::table('bundle_has_services')
                    ->whereIn('id', $deleteIds)
                    ->delete();
            }
        }
    }
};
