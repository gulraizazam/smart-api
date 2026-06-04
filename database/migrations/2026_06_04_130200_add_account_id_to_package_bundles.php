<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add the tenant column `account_id` to `package_bundles`.
 *
 * Every other plan/catalog table (`packages`, `bundles`,
 * `service_bundles`, `package_advances`) carries `account_id`, but
 * `package_bundles` never did — its rows were scoped only indirectly,
 * via the `random_id` string or the parent `package_id`. That makes a
 * `random_id`-only lookup (PlanService::addServiceToPackage) implicitly
 * trust the string to be globally unique.
 *
 * This migration only ADDS the column (nullable) + an index. It is
 * additive and crm2-safe: crm2's PackageBundles model has no
 * `account_id` in its fillable, so crm2 inserts simply leave it NULL —
 * which a nullable column permits. The backfill is a separate sibling
 * migration; tightening the read paths to filter by account_id is
 * deliberately deferred (it needs an off-hours coexistence test so
 * crm2's NULL-account rows stay visible).
 *
 * No FK constraint this round (matches the "safe-now" scope and the
 * sibling unconstrained id columns on this table).
 *
 * Idempotent guard mirrors 2026_05_15_140000_add_order_id_to_package_advances.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('package_bundles', function (Blueprint $table) {
            if (! Schema::hasColumn('package_bundles', 'account_id')) {
                $table->unsignedInteger('account_id')->nullable()->after('package_id');
            }
        });

        Schema::table('package_bundles', function (Blueprint $table) {
            $existing = collect(Schema::getIndexes('package_bundles'))->pluck('name')->all();
            if (! in_array('idx_package_bundles_account', $existing, true)) {
                $table->index('account_id', 'idx_package_bundles_account');
            }
        });
    }

    public function down(): void
    {
        Schema::table('package_bundles', function (Blueprint $table) {
            $existing = collect(Schema::getIndexes('package_bundles'))->pluck('name')->all();
            if (in_array('idx_package_bundles_account', $existing, true)) {
                $table->dropIndex('idx_package_bundles_account');
            }
        });

        Schema::table('package_bundles', function (Blueprint $table) {
            if (Schema::hasColumn('package_bundles', 'account_id')) {
                $table->dropColumn('account_id');
            }
        });
    }
};
