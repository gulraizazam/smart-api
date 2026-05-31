<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Drops fk_pkg_bundles_bundle from package_bundles.
 *
 * package_bundles.bundle_id is a polymorphic pointer driven by
 * package_bundles.source_type ('service' | 'bundle' | 'membership'),
 * so a single FK to bundles(id) is invalid and blocks every non-bundle
 * package insert/update (error 1452).
 *
 * Forward-fix: drop the FK. Integrity is enforced in application code via
 * source_type + bundle_id lookups.
 */
return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::selectOne(
            "SELECT CONSTRAINT_NAME
               FROM information_schema.TABLE_CONSTRAINTS
              WHERE CONSTRAINT_SCHEMA = DATABASE()
                AND TABLE_NAME = 'package_bundles'
                AND CONSTRAINT_NAME = 'fk_pkg_bundles_bundle'
                AND CONSTRAINT_TYPE = 'FOREIGN KEY'
              LIMIT 1"
        );

        if ($exists) {
            Schema::table('package_bundles', function ($table) {
                $table->dropForeign('fk_pkg_bundles_bundle');
            });
        }
    }

    public function down(): void
    {
        // Intentionally not re-added: the original FK was incorrect for the
        // polymorphic bundle_id column. Recreating it would re-introduce the
        // 1452 error on every service/membership package save.
    }
};
