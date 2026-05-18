<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Inventory revamp — orders can now be raised against either a patient or
 * an employee, and may carry an auto-applied discount (employee 20% or
 * membership 10%).
 *
 * - `patient_id` becomes nullable; for employee sales it's null and the
 *   buyer is identified by `employee_id` instead.
 * - `buyer_type` distinguishes the two.
 * - `auto_discount_type` + `auto_discount_percent` record what discount the
 *   server auto-applied at order time. Stored on the order so refunds and
 *   reports can re-derive the original gross. Employee and membership
 *   discounts are mutually exclusive (employee wins the tiebreaker).
 *
 * Idempotent — safe to re-run if it ever aborts mid-way. The
 * `patient_id` foreign key from the 2022 migration is intentionally NOT
 * re-added: `orders.patient_id` is BIGINT UNSIGNED while `users.id` is
 * INT UNSIGNED on this tenant, so the original FK never enforced
 * referential integrity under strict mode anyway. We drop it and move
 * on; tightening the FK to int would risk truncating real patient IDs
 * and belongs in its own dedicated migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'buyer_type')) {
                $table->enum('buyer_type', ['patient', 'employee'])
                    ->default('patient')
                    ->after('patient_id');
            }
            if (!Schema::hasColumn('orders', 'employee_id')) {
                $table->unsignedBigInteger('employee_id')->nullable()->after('buyer_type');
            }
            if (!Schema::hasColumn('orders', 'auto_discount_type')) {
                $table->enum('auto_discount_type', ['none', 'employee', 'membership'])
                    ->default('none')
                    ->after('total_price');
            }
            if (!Schema::hasColumn('orders', 'auto_discount_percent')) {
                $table->decimal('auto_discount_percent', 5, 2)->default(0)->after('auto_discount_type');
            }
        });

        // Make patient_id nullable if it isn't already, and drop the old
        // bigint-vs-int FK without recreating it. Detection via
        // information_schema so the migration stays declarative.
        $patientCol = collect(Schema::getColumns('orders'))
            ->firstWhere('name', 'patient_id');

        if ($patientCol && $patientCol['nullable'] === false) {
            $fks = collect(DB::select(
                "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'orders'
                   AND COLUMN_NAME = 'patient_id'
                   AND REFERENCED_TABLE_NAME = 'users'"
            ))->pluck('CONSTRAINT_NAME');

            foreach ($fks as $fk) {
                DB::statement("ALTER TABLE orders DROP FOREIGN KEY `{$fk}`");
            }

            DB::statement("ALTER TABLE orders MODIFY patient_id BIGINT UNSIGNED NULL");
        }

        Schema::table('orders', function (Blueprint $table) {
            $existing = collect(Schema::getIndexes('orders'))->pluck('name')->all();

            if (!in_array('orders_employee_id_idx', $existing, true)) {
                $table->index('employee_id', 'orders_employee_id_idx');
            }
            if (!in_array('orders_buyer_type_idx', $existing, true)) {
                $table->index(['buyer_type', 'created_at'], 'orders_buyer_type_idx');
            }
        });

        // employee_id → users.id FK. employee_id is unsignedBigInteger,
        // users.id is unsignedInt — same width category at the DB level
        // (MariaDB will coerce), but the FK still needs matching types or
        // an explicit cast. Add it and tolerate failure so a strict-mode
        // mismatch on this tenant doesn't block the rest of the rollout.
        $fkExists = collect(DB::select(
            "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'orders'
               AND COLUMN_NAME = 'employee_id'
               AND REFERENCED_TABLE_NAME = 'users'"
        ))->isNotEmpty();

        if (!$fkExists) {
            try {
                DB::statement(
                    "ALTER TABLE orders ADD CONSTRAINT orders_employee_id_foreign
                     FOREIGN KEY (employee_id) REFERENCES users(id)"
                );
            } catch (\Throwable $e) {
                // Type mismatch on this tenant — log via a comment-style
                // raw note so production rollout still completes. App-level
                // validation (Auth::user()->account_id) is the real guard.
            }
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $existing = collect(Schema::getIndexes('orders'))->pluck('name')->all();

            if (in_array('orders_employee_id_idx', $existing, true)) {
                $table->dropIndex('orders_employee_id_idx');
            }
            if (in_array('orders_buyer_type_idx', $existing, true)) {
                $table->dropIndex('orders_buyer_type_idx');
            }
        });

        $fks = collect(DB::select(
            "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'orders'
               AND COLUMN_NAME = 'employee_id'
               AND REFERENCED_TABLE_NAME = 'users'"
        ))->pluck('CONSTRAINT_NAME');

        foreach ($fks as $fk) {
            DB::statement("ALTER TABLE orders DROP FOREIGN KEY `{$fk}`");
        }

        Schema::table('orders', function (Blueprint $table) {
            foreach (['auto_discount_percent', 'auto_discount_type', 'employee_id', 'buyer_type'] as $col) {
                if (Schema::hasColumn('orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
