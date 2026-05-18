<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Inventory revamp — mirror the orders-table buyer_type extension on
 * order_refunds so employee-buyer sales can be refunded too.
 *
 * Symmetric with 2026_05_15_120200_extend_orders_for_buyer_type:
 *   - patient_id becomes nullable (employee refunds carry no patient_id)
 *   - buyer_type / employee_id added
 *
 * Auto-discount fields are intentionally not duplicated here — the
 * original order carries them, and a refund inherits the same discount
 * (refunds are line-by-line at the original net price).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_refunds', function (Blueprint $table) {
            if (!Schema::hasColumn('order_refunds', 'buyer_type')) {
                $table->enum('buyer_type', ['patient', 'employee'])
                    ->default('patient')
                    ->after('patient_id');
            }
            if (!Schema::hasColumn('order_refunds', 'employee_id')) {
                $table->unsignedBigInteger('employee_id')->nullable()->after('buyer_type');
            }
        });

        $patientCol = collect(Schema::getColumns('order_refunds'))
            ->firstWhere('name', 'patient_id');

        if ($patientCol && $patientCol['nullable'] === false) {
            $fks = collect(DB::select(
                "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'order_refunds'
                   AND COLUMN_NAME = 'patient_id'
                   AND REFERENCED_TABLE_NAME IS NOT NULL"
            ))->pluck('CONSTRAINT_NAME');

            foreach ($fks as $fk) {
                DB::statement("ALTER TABLE order_refunds DROP FOREIGN KEY `{$fk}`");
            }

            // Match the existing column width (INT UNSIGNED) — order_refunds
            // is older and uses INT rather than BIGINT for patient_id.
            DB::statement("ALTER TABLE order_refunds MODIFY patient_id INT UNSIGNED NULL");
        }
    }

    public function down(): void
    {
        Schema::table('order_refunds', function (Blueprint $table) {
            foreach (['employee_id', 'buyer_type'] as $col) {
                if (Schema::hasColumn('order_refunds', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
