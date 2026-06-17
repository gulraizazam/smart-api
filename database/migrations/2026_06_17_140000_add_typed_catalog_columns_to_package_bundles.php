<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * De-overload `package_bundles.bundle_id` — STEP 1 of 2 (columns + backfill;
 * FK constraints land in the follow-up after a per-env orphan re-check).
 *
 * Today `bundle_id` is an OVERLOADED FK whose meaning depends on `source_type`
 * (service -> services.id, bundle -> bundles.id, service_bundle ->
 * service_bundles.id). Ids collide across those tables, so an unguarded reader
 * can resolve the WRONG catalog -> wrong name/price on invoices/reports.
 *
 * This adds three single-purpose, table-aligned columns and mechanically MIRRORS
 * the existing (bundle_id, source_type) into them. It is ADDITIVE and history-
 * preserving: `bundle_id` and `source_type` are LEFT UNTOUCHED as a fallback, and
 * NO row is re-classified (single-service "Packages" stay Packages — that is
 * correct history from before the Bundle module existed). Readers are repointed
 * separately; nothing ships until the before/after name+price reconcile is zero-diff.
 *
 * Column types MUST match the referenced PKs (FK requirement):
 *   services.id        = INT  unsigned  -> catalog_service_id        INT  unsigned
 *   bundles.id         = INT  unsigned  -> catalog_bundle_id         INT  unsigned
 *   service_bundles.id = BIGINT unsigned-> catalog_service_bundle_id BIGINT unsigned
 *
 * Names are TABLE-aligned (column suffix == referenced table) so the schema is
 * unambiguous; the UI inversion (bundles = "Package", service_bundles = "Bundle")
 * is documented in the glossary, not encoded here. Membership lines
 * (membership_type_id set, source_type NULL) keep all three columns NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('package_bundles', function (Blueprint $table): void {
            $table->unsignedInteger('catalog_service_id')->nullable()->after('bundle_id');
            $table->unsignedInteger('catalog_bundle_id')->nullable()->after('catalog_service_id');
            $table->unsignedBigInteger('catalog_service_bundle_id')->nullable()->after('catalog_bundle_id');

            $table->index('catalog_service_id', 'idx_pb_catalog_service');
            $table->index('catalog_bundle_id', 'idx_pb_catalog_bundle');
            $table->index('catalog_service_bundle_id', 'idx_pb_catalog_service_bundle');
        });

        // Mechanical mirror of the current resolution. Membership rows excluded
        // (they key off membership_type_id with source_type NULL).
        DB::table('package_bundles')->whereNull('membership_type_id')
            ->where('source_type', 'service')
            ->update(['catalog_service_id' => DB::raw('bundle_id')]);

        DB::table('package_bundles')->whereNull('membership_type_id')
            ->where('source_type', 'bundle')
            ->update(['catalog_bundle_id' => DB::raw('bundle_id')]);

        DB::table('package_bundles')->whereNull('membership_type_id')
            ->where('source_type', 'service_bundle')
            ->update(['catalog_service_bundle_id' => DB::raw('bundle_id')]);
    }

    public function down(): void
    {
        Schema::table('package_bundles', function (Blueprint $table): void {
            $table->dropIndex('idx_pb_catalog_service');
            $table->dropIndex('idx_pb_catalog_bundle');
            $table->dropIndex('idx_pb_catalog_service_bundle');
            $table->dropColumn(['catalog_service_id', 'catalog_bundle_id', 'catalog_service_bundle_id']);
        });
    }
};
