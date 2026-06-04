<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Backfill `package_bundles.account_id` from the owning package.
 *
 * Runs after 2026_06_04_130200_add_account_id_to_package_bundles.
 * Two passes (verified shape on the prod-mirror local DB: 134,445 rows
 * resolve by package_id, 12,004 in-flight rows resolve by random_id,
 * 0 left stuck):
 *   1. rows linked to a package (package_id set) — copy that package's
 *      account_id.
 *   2. in-flight "cart" rows (package_id NULL) — resolve via random_id.
 *
 * `packages.account_id` is NOT NULL, so every resolved row gets a real
 * tenant. Any residual NULLs are un-allocated carts with no matching
 * package yet; the PackageBundles `creating` hook stamps account_id on
 * all new rows, so the residual set only shrinks.
 *
 * Additive + crm2-safe: only writes a previously-NULL column.
 * Idempotent: a second run finds no NULL rows to update.
 *
 * Down: irreversible — backfill only fills a NULL column.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('package_bundles', 'account_id')) {
            // Column migration hasn't run — nothing to backfill.
            return;
        }

        // Pass 1 — resolve via the linked package.
        $byPackageId = DB::update(
            'UPDATE package_bundles pb '
            .'JOIN packages p ON p.id = pb.package_id '
            .'SET pb.account_id = p.account_id '
            .'WHERE pb.account_id IS NULL AND pb.package_id IS NOT NULL'
        );

        // Pass 2 — in-flight rows not yet linked to a package: match by random_id.
        $byRandomId = DB::update(
            'UPDATE package_bundles pb '
            .'JOIN packages p ON p.random_id = pb.random_id '
            .'SET pb.account_id = p.account_id '
            .'WHERE pb.account_id IS NULL AND pb.package_id IS NULL AND pb.random_id IS NOT NULL'
        );

        $residual = DB::table('package_bundles')->whereNull('account_id')->count();

        \Log::info('backfill_package_bundles_account_id', [
            'by_package_id' => $byPackageId,
            'by_random_id'  => $byRandomId,
            'residual_null' => $residual,
        ]);
    }

    public function down(): void
    {
        // Irreversible — see class docblock.
    }
};
