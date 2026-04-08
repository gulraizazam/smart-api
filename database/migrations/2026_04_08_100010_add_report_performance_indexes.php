<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add indexes identified by EXPLAIN ANALYZE on report queries.
     *
     * EXPLAIN findings:
     *   package_advances WHERE account_id=? AND created_at BETWEEN → full scan (826K rows)
     *   package_advances WHERE account_id=? AND location_id=? AND created_at → partial scan
     *   invoices WHERE account_id=? AND deleted_at IS NULL ORDER BY created_at → filesort on 245K rows
     */
    public function up(): void
    {
        Schema::table('package_advances', function (Blueprint $table) {
            // Covers: ledger queries, collection reports, refund reports
            $table->index(['account_id', 'created_at'], 'idx_pa_account_created');
        });

        Schema::table('invoices', function (Blueprint $table) {
            // Covers: datatable ORDER BY created_at with account + soft-delete filter
            $table->index(['account_id', 'deleted_at', 'created_at'], 'idx_invoices_account_deleted_created');
        });
    }

    public function down(): void
    {
        Schema::table('package_advances', fn (Blueprint $table) => $table->dropIndex('idx_pa_account_created'));
        Schema::table('invoices', fn (Blueprint $table) => $table->dropIndex('idx_invoices_account_deleted_created'));
    }
};
