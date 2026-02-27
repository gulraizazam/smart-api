<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddSourceTypeToPackageBundlesTable extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds source_type column to package_bundles to distinguish what bundle_id contains:
     * - 'service'    = bundle_id holds a services.id (new plan-type records)
     * - 'bundle'     = bundle_id holds a bundles.id (old plan-type records + all bundle-type records)
     * - 'membership' = row is membership-based (uses membership_type_id)
     *
     * @return void
     */
    public function up()
    {
        // Step 1: Add the column (skip if already exists from partial run)
        if (!Schema::hasColumn('package_bundles', 'source_type')) {
            Schema::table('package_bundles', function (Blueprint $table) {
                $table->string('source_type', 20)->nullable()->after('bundle_id')
                      ->comment('service|bundle|membership - what bundle_id column references');
            });
        }

        // Step 2: Backfill source_type for ALL rows in a single UPDATE
        // NOTE: plan_type column was added recently with default 'plan', so ALL old records
        //       (which had bundles) now show plan_type = 'plan'. We CANNOT trust plan_type.
        //       Instead we use package_services child rows to detect truth:
        //   - membership_type_id IS NOT NULL → 'membership'
        //   - child_count = 1 AND service_id == bundle_id → 'service' (new flow: service stored directly)
        //   - child_count > 1 → 'bundle' (old flow: bundle had multiple child services)
        //   - child_count = 1 AND service_id != bundle_id → 'bundle' (old flow: bundle with single child service)
        //   - No children at all → check if bundle_id exists in bundles table first (safer for old data)
        DB::statement("
            UPDATE package_bundles pb
            LEFT JOIN (
                SELECT package_bundle_id,
                       COUNT(*) as child_count,
                       MAX(service_id) as single_service_id
                FROM package_services
                GROUP BY package_bundle_id
            ) ps_agg ON ps_agg.package_bundle_id = pb.id
            SET pb.source_type = CASE
                WHEN pb.membership_type_id IS NOT NULL THEN 'membership'
                WHEN ps_agg.child_count = 1 AND ps_agg.single_service_id = pb.bundle_id THEN 'service'
                WHEN ps_agg.child_count > 1 THEN 'bundle'
                WHEN ps_agg.child_count = 1 AND ps_agg.single_service_id != pb.bundle_id THEN 'bundle'
                WHEN ps_agg.child_count IS NULL THEN
                    CASE WHEN EXISTS (SELECT 1 FROM bundles b WHERE b.id = pb.bundle_id)
                         THEN 'bundle' ELSE 'service' END
                ELSE 'service'
            END
            WHERE pb.deleted_at IS NULL
        ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('package_bundles', function (Blueprint $table) {
            $table->dropColumn('source_type');
        });
    }
}
