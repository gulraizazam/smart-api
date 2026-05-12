<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The expense unflag flow (CashFlowExpensesController::expensesUnflag) writes
 * an audit row with action='unflagged'. The `cashflow_audit_logs.action`
 * column is a strict ENUM that did not include this value, so the audit
 * insert raised SQLSTATE 22007 / 1265 ("Data truncated for column 'action'")
 * and the controller surfaced "An error occurred. Please try again." to the
 * client. Same class of bug the 2026_04_23_130000 migration fixed for
 * 'deleted'.
 *
 * Append 'unflagged' to the allowed set. Additive ENUM ALTER on MariaDB 11 is
 * an in-place metadata change — no table rewrite, safe on prod-sized data.
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
                'auto_created', 'reset', 'deleted', 'unflagged'
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
                'auto_created', 'reset', 'deleted'
            ) NOT NULL
        ");
    }
};
