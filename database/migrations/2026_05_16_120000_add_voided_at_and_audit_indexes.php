<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive, online-safe indexes (no data change). Mirrors the
 * `expenses.voided_at` index onto the other movement tables — every
 * MovementService::query* filters `whereNull/whereNotNull('voided_at')`,
 * and a composite `(account_id, voided_at)` keeps that sargable. Also
 * adds the covering index `cashflow_audit_logs` needs for the
 * `Expense::lastEditLog()` latest-of-many subquery.
 *
 * Part of the cutover replay set — pure index adds, reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['cash_transfers', 'staff_advances', 'staff_returns', 'staff_transfers'] as $table) {
            if (Schema::hasColumn($table, 'voided_at')) {
                Schema::table($table, function (Blueprint $t) use ($table) {
                    $t->index(['account_id', 'voided_at'], "{$table}_account_voided_idx");
                });
            }
        }

        if (Schema::hasTable('cashflow_audit_logs')) {
            Schema::table('cashflow_audit_logs', function (Blueprint $t) {
                $t->index(['entity_type', 'entity_id', 'action'], 'cf_audit_entity_action_idx');
            });
        }
    }

    public function down(): void
    {
        foreach (['cash_transfers', 'staff_advances', 'staff_returns', 'staff_transfers'] as $table) {
            if (Schema::hasColumn($table, 'voided_at')) {
                Schema::table($table, function (Blueprint $t) use ($table) {
                    $t->dropIndex("{$table}_account_voided_idx");
                });
            }
        }

        if (Schema::hasTable('cashflow_audit_logs')) {
            Schema::table('cashflow_audit_logs', function (Blueprint $t) {
                $t->dropIndex('cf_audit_entity_action_idx');
            });
        }
    }
};
