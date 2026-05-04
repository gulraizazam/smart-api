<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Speeds up the refunds datatable.
     *
     * The refund list query (`PackageAdvances::getRefundedRecords`) does:
     *   WHERE is_refund = 1
     *     AND location_id IN (...)
     *     AND active = 1   -- when the operator lacks `view_inactive_plans`
     *   GROUP BY package_id
     *   ORDER BY MAX(created_at) DESC
     *   LIMIT 25
     *
     * Existing indexes on this table cover (package_id, deleted_at) and
     * single-column location_id / patient_id, but nothing leads on
     * `is_refund`. With it, the planner has to scan every row whose
     * location is visible to the operator and then filter — for a real
     * dataset that's the whole `package_advances` table. A composite
     * starting on `is_refund` lets the planner skip straight to refund
     * rows for the visible centres.
     *
     * Pairs with the simultaneous service-level fix that folds the
     * per-row `latestRefund` lookup into the existing batched aggregate
     * query — the index here closes out the remaining query-plan cost.
     */
    public function up(): void
    {
        if (! $this->indexExists('package_advances', 'idx_pa_refund_location_pkg')) {
            Schema::table('package_advances', function (Blueprint $table) {
                $table->index(
                    ['is_refund', 'location_id', 'package_id'],
                    'idx_pa_refund_location_pkg',
                );
            });
        }
    }

    public function down(): void
    {
        Schema::table('package_advances', function (Blueprint $table) {
            $table->dropIndexIfExists('idx_pa_refund_location_pkg');
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $indexes = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);
        return count($indexes) > 0;
    }
};
