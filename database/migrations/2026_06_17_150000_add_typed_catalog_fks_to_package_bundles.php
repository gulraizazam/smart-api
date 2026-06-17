<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De-overload package_bundles.bundle_id — STEP 2 of 2: the FK constraints.
 *
 * Follows 2026_06_17_140000 (typed columns + backfill). Verified prerequisite:
 * 0 orphans across all three typed columns (every non-null value resolves to
 * its catalog PK), so the constraints apply with no data cleanup. Column types
 * match their targets (services.id / bundles.id = INT unsigned;
 * service_bundles.id = BIGINT unsigned). The referencing indexes already exist
 * (idx_pb_catalog_*), added by the columns migration.
 *
 * ON DELETE RESTRICT: services, bundles and service_bundles all use SoftDeletes
 * — a soft-delete is an UPDATE (never triggers an FK on-delete) and the app does
 * NO hard-delete of these tables (verified). So RESTRICT never fires in normal
 * operation; it only blocks an accidental hard-delete from erasing a catalog row
 * that a plan line references — preserving financial history. The legacy
 * bundle_id stays unconstrained as the documented fallback.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('package_bundles', function (Blueprint $table): void {
            $table->foreign('catalog_service_id', 'fk_pb_catalog_service')
                ->references('id')->on('services')->restrictOnDelete();
            $table->foreign('catalog_bundle_id', 'fk_pb_catalog_bundle')
                ->references('id')->on('bundles')->restrictOnDelete();
            $table->foreign('catalog_service_bundle_id', 'fk_pb_catalog_service_bundle')
                ->references('id')->on('service_bundles')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('package_bundles', function (Blueprint $table): void {
            $table->dropForeign('fk_pb_catalog_service');
            $table->dropForeign('fk_pb_catalog_bundle');
            $table->dropForeign('fk_pb_catalog_service_bundle');
        });
    }
};
