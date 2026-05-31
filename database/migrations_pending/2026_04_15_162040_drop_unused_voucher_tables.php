<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The original `Voucher` model + `vouchers` table were superseded by
 * the unified `discounts` table (with discount_type='voucher'). The
 * legacy tables were never written to in the current code path —
 * verified by grepping for create() / insert() statements at the time
 * this migration was written. The model and factory were also removed
 * in the same PR. This migration drops the now-orphan schema.
 *
 * Rollback: the down() recreates empty schema only. Original data is
 * not restorable from this migration — the assumption is that the
 * tables have been empty since the unification. If any environment
 * still has rows in vouchers / voucher_has_locations, restore from
 * backup before rolling back.
 */
return new class extends Migration
{
    public function up(): void
    {
        // user_vouchers + package_vouchers may carry stale FKs that
        // reference the legacy `vouchers` table (e.g. fk_user_vouchers_voucher_id,
        // fk_pkg_vouchers_voucher). Vouchers were unified into the
        // `discounts` table — these FKs are dead weight blocking the
        // table drop. Names vary across environments, so introspect
        // INFORMATION_SCHEMA and drop any FK whose REFERENCED_TABLE_NAME
        // is `vouchers`.
        $this->dropForeignKeysReferencingVouchers();

        Schema::dropIfExists('voucher_has_services');
        Schema::dropIfExists('voucher_has_locations');
        Schema::dropIfExists('vouchers');
    }

    private function dropForeignKeysReferencingVouchers(): void
    {
        $rows = DB::select(
            "SELECT TABLE_NAME, CONSTRAINT_NAME
             FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND REFERENCED_TABLE_NAME = 'vouchers'"
        );

        foreach ($rows as $row) {
            DB::statement("ALTER TABLE `{$row->TABLE_NAME}` DROP FOREIGN KEY `{$row->CONSTRAINT_NAME}`");
        }
    }

    public function down(): void
    {
        Schema::create('vouchers', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 40)->default('default');
            $table->string('name')->nullable();
            $table->date('start')->nullable();
            $table->date('end')->nullable();
            $table->unsignedTinyInteger('active')->default(1);
            $table->unsignedBigInteger('account_id')->nullable();
            $table->foreign('account_id')->references('id')->on('accounts');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('voucher_has_locations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('voucher_id');
            $table->unsignedBigInteger('location_id');
            $table->unsignedBigInteger('service_id');
            $table->timestamps();
            $table->foreign('voucher_id')->references('id')->on('vouchers')->cascadeOnDelete();
            $table->foreign('location_id')->references('id')->on('locations')->cascadeOnDelete();
            $table->foreign('service_id')->references('id')->on('services')->cascadeOnDelete();
        });

        Schema::create('voucher_has_services', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
        });
    }
};
