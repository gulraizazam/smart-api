<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The `cashflow_audit_logs.action` column is an ENUM whose allowed values
 * were frozen when the table was created (migration
 * 2026_03_05_100013). The model (CashflowAuditLog::ACTION_DELETED) and the
 * vendor-transaction + cash-pool delete flows insert the value 'deleted',
 * which the ENUM rejected — the delete would succeed (DB::transaction had
 * already committed the row removal) but the subsequent audit write threw,
 * surfacing "An error occurred" to the client even though the record was
 * gone.
 *
 * Add 'deleted' to the allowed set. MariaDB 11 supports in-place ALTER for
 * ENUM when the change is additive (values appended) — no table rewrite.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE `cashflow_audit_logs`
            MODIFY `action` ENUM(
                'created', 'updated', 'voided', 'approved', 'rejected',
                'resubmitted', 'locked', 'unlocked', 'deactivated',
                'auto_created', 'reset', 'deleted'
            ) NOT NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE `cashflow_audit_logs`
            MODIFY `action` ENUM(
                'created', 'updated', 'voided', 'approved', 'rejected',
                'resubmitted', 'locked', 'unlocked', 'deactivated',
                'auto_created', 'reset'
            ) NOT NULL
        ");
    }
};
