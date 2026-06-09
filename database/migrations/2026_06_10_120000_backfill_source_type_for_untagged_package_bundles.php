<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill `package_bundles.source_type` for plan lines that were created
 * AFTER the one-time 2026-02-27 backfill and never got tagged. The live
 * create path in both apps stopped stamping it, so ~4k rows (almost all
 * crm2-era, created before the crm3 cutover) sit NULL.
 *
 * source_type disambiguates the overloaded `bundle_id` (a services.id vs a
 * bundles.id — the two can share the same number), so an untagged line can
 * mis-resolve on invoices / financial reports / name lookups. This
 * re-applies the EXACT heuristic the 2026-02-27 migration used, scoped to
 * the remaining blanks.
 *
 * Coexistence-safe + additive: it ONLY fills blank labels (never overwrites
 * an existing one), touches no money/price/tax column, and leaves
 * memberships NULL (they use `membership_type_id` and carry a NULL
 * `bundle_id`, so they have no collision risk — matches crm2). Idempotent:
 * re-running after the blanks are filled is a no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            UPDATE package_bundles pb
            LEFT JOIN (
                SELECT package_bundle_id,
                       COUNT(*) AS child_count,
                       MAX(service_id) AS single_service_id
                FROM package_services
                GROUP BY package_bundle_id
            ) ps_agg ON ps_agg.package_bundle_id = pb.id
            SET pb.source_type = CASE
                WHEN ps_agg.child_count = 1 AND ps_agg.single_service_id = pb.bundle_id THEN 'service'
                WHEN ps_agg.child_count > 1 THEN 'bundle'
                WHEN ps_agg.child_count = 1 AND ps_agg.single_service_id <> pb.bundle_id THEN 'bundle'
                WHEN ps_agg.child_count IS NULL THEN
                    CASE WHEN EXISTS (SELECT 1 FROM bundles b WHERE b.id = pb.bundle_id)
                         THEN 'bundle' ELSE 'service' END
                ELSE 'service'
            END
            WHERE pb.source_type IS NULL
              AND pb.membership_type_id IS NULL
              AND pb.deleted_at IS NULL
        ");
    }

    public function down(): void
    {
        // One-way data backfill. Re-nulling would wrongly blank rows that
        // legitimately carry a label (including ones crm2 wrote), so down
        // is intentionally a no-op.
    }
};
