<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add indexes to speed up the global plans datatable query.
     *
     * Key bottlenecks addressed:
     * 1. packages composite index — covers the account_id + location_id + active + deleted_at
     *    filter used in both the outer WHERE and the scoped package-ID subquery.
     * 2. package_advances(package_id) — speeds up GROUP BY in the pa_agg subquery.
     * 3. package_bundles(package_id) — speeds up GROUP BY in the pb_agg subquery.
     * 4. package_services(package_id) — speeds up GROUP BY in the ps_agg subquery.
     */
    public function up(): void
    {
        // Composite index for the main packages filter (account + location + active + soft-delete)
        if (!$this->indexExists('packages', 'idx_packages_account_location_active')) {
            Schema::table('packages', function (Blueprint $table) {
                $table->index(['account_id', 'location_id', 'active', 'deleted_at'], 'idx_packages_account_location_active');
            });
        }

        // Index on packages.updated_at for ORDER BY (used as sort fallback)
        if (!$this->indexExists('packages', 'idx_packages_updated_at')) {
            Schema::table('packages', function (Blueprint $table) {
                $table->index('updated_at', 'idx_packages_updated_at');
            });
        }

        // package_advances.package_id — needed by pa_agg subquery GROUP BY
        if (!$this->indexExists('package_advances', 'idx_package_advances_package_id')) {
            Schema::table('package_advances', function (Blueprint $table) {
                $table->index('package_id', 'idx_package_advances_package_id');
            });
        }

        // package_advances composite — covers the WHERE + GROUP BY in pa_agg subquery
        if (!$this->indexExists('package_advances', 'idx_pa_pkg_deleted')) {
            Schema::table('package_advances', function (Blueprint $table) {
                $table->index(['package_id', 'deleted_at'], 'idx_pa_pkg_deleted');
            });
        }

        // package_bundles.package_id — needed by pb_agg subquery GROUP BY
        if (!$this->indexExists('package_bundles', 'idx_package_bundles_package_id')) {
            Schema::table('package_bundles', function (Blueprint $table) {
                $table->index('package_id', 'idx_package_bundles_package_id');
            });
        }

        // package_services.package_id — needed by ps_agg subquery GROUP BY
        if (!$this->indexExists('package_services', 'idx_package_services_package_id')) {
            Schema::table('package_services', function (Blueprint $table) {
                $table->index('package_id', 'idx_package_services_package_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropIndexIfExists('idx_packages_account_location_active');
            $table->dropIndexIfExists('idx_packages_updated_at');
        });

        Schema::table('package_advances', function (Blueprint $table) {
            $table->dropIndexIfExists('idx_package_advances_package_id');
            $table->dropIndexIfExists('idx_pa_pkg_deleted');
        });

        Schema::table('package_bundles', function (Blueprint $table) {
            $table->dropIndexIfExists('idx_package_bundles_package_id');
        });

        Schema::table('package_services', function (Blueprint $table) {
            $table->dropIndexIfExists('idx_package_services_package_id');
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $indexes = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);
        return count($indexes) > 0;
    }
};
