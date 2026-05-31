<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vouchers hardening migration:
 *
 *   1. Fixes the precision drift on package_vouchers.amount — production
 *      schema has it as DECIMAL(10,0), silently truncating partial
 *      currency redemptions. Bump to DECIMAL(10,2) to match the
 *      user_vouchers.amount column and the Eloquent decimal:2 cast.
 *   2. Adds indexes on the columns the redemption + datatable queries
 *      filter on (voucher_id, user_id, package_random_id).
 *   3. Adds softDeletes() to user_vouchers so the assignment ledger
 *      keeps an audit trail when a voucher is removed from a patient.
 *
 * FK constraints are intentionally NOT added: voucher_id columns are
 * BIGINT UNSIGNED but `discounts.id` is INT UNSIGNED in the legacy
 * schema, and altering either side risks truncating live data. The
 * indexes alone deliver the query-performance win; orphan-row cleanup
 * is tracked separately in the rolling data-hygiene task.
 *
 * Rollback: down() drops the indexes / softDeletes column and reverts
 * package_vouchers.amount precision.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Production drift: schema dump shows package_vouchers.amount as
        // decimal(10,0) — partial-rupee redemptions truncate. Fix to
        // decimal(10,2) to match user_vouchers.amount and the model cast.
        DB::statement('ALTER TABLE `package_vouchers` MODIFY COLUMN `amount` DECIMAL(10,2) NOT NULL DEFAULT 0');

        Schema::table('user_vouchers', function (Blueprint $table): void {
            $table->softDeletes();

            $table->index(['user_id', 'voucher_id'], 'user_vouchers_user_voucher_index');
            $table->index('voucher_id', 'user_vouchers_voucher_id_index');
        });

        Schema::table('package_vouchers', function (Blueprint $table): void {
            $table->index(['voucher_id', 'user_id'], 'package_vouchers_voucher_user_index');
            $table->index('package_random_id', 'package_vouchers_package_random_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('package_vouchers', function (Blueprint $table): void {
            $table->dropIndex('package_vouchers_voucher_user_index');
            $table->dropIndex('package_vouchers_package_random_id_index');
        });

        Schema::table('user_vouchers', function (Blueprint $table): void {
            $table->dropIndex('user_vouchers_user_voucher_index');
            $table->dropIndex('user_vouchers_voucher_id_index');
            $table->dropSoftDeletes();
        });

        DB::statement('ALTER TABLE `package_vouchers` MODIFY COLUMN `amount` DECIMAL(10,0) NOT NULL DEFAULT 0');
    }
};
