<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Re-backfill `package_bundles.account_id` for rows still NULL after the
 * one-off 2026_06_04_130300 backfill.
 *
 * Why a SECOND pass is needed: unlike `users.phone_normalized` (which has a
 * DB trigger), `package_bundles.account_id` is stamped only by the crm3
 * Eloquent `creating` hook. Any row written WITHOUT that hook — a legacy crm2
 * plan save on the shared DB, or a raw insert — lands with account_id NULL, so
 * the residual keeps growing after the original backfill. A NULL account_id is
 * invisible to crm3's tenant-scoped delete validation
 * (`Rule::exists('package_bundles','id')->where('account_id', $accountId)`): the
 * plan opens in the editor (the edit query scopes by package_id, not
 * account_id) but deleting a service fails with "The selected ids.0 is invalid".
 * Re-running the resolution lets those plans delete again.
 *
 * Same two passes as 2026_06_04_130300:
 *   1. rows linked to a package (package_id set) — copy that package's account_id.
 *   2. in-flight "cart" rows (package_id NULL) — resolve via random_id.
 *
 * `packages.account_id` is NOT NULL, so every resolved row gets a real tenant.
 * Residual NULLs are un-allocated carts with no matching package yet.
 *
 * Additive + crm2-safe: only writes a previously-NULL column crm2 ignores.
 * Idempotent: a second run finds no NULL rows to update.
 * Down: irreversible — a backfill only fills a NULL column.
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

        \Log::info('backfill_residual_package_bundles_account_id', [
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
