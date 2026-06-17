<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill account_id on the package_bundles rows the original by-package /
 * random_id backfill (2026_06_04_130300) could not reach — the unallocated /
 * staged rows (package_id NULL) that never matched a saved package. They all
 * carry a location_id, so the tenant is derivable from the location.
 *
 * Closes the NULL-account_id gap that broke the remove-service flow once a
 * staged row was later allocated to a plan (e.g. plan 50611, fixed on prod by
 * hand) and unblocks a future `account_id` NOT NULL. ADDITIVE: only fills rows
 * whose account_id is currently NULL; never overwrites an existing value.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('package_bundles')
            ->join('locations', 'package_bundles.location_id', '=', 'locations.id')
            ->whereNull('package_bundles.account_id')
            ->whereNotNull('locations.account_id')
            ->update(['package_bundles.account_id' => DB::raw('locations.account_id')]);
    }

    public function down(): void
    {
        // Data backfill — not structurally reversible (the pre-backfill NULL set
        // is not recorded). Intentional no-op.
    }
};
