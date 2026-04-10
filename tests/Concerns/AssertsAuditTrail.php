<?php

declare(strict_types=1);

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Assert;

/**
 * Helpers for asserting that the CashFlow / AuditTrails write-side has
 * captured the expected immutable record. Used by every observer test
 * and by every CashFlow feature test that exercises a state transition.
 *
 * The audit-log row IS part of the contract — if a refactor accidentally
 * skips writing it, the financial-compliance reviewer reading the
 * cashflow_audit_logs table will see a hole. These assertions are how
 * we catch that before the refactor lands.
 */
trait AssertsAuditTrail
{
    /**
     * Assert that exactly one row exists in `cashflow_audit_logs` for the
     * given (entity_type, entity_id, action) tuple. Used after a state
     * transition (create / approve / void / etc.) on a CashFlow domain
     * model.
     */
    protected function assertCashflowAuditLogged(
        string $entityType,
        int $entityId,
        string $action,
        ?int $userId = null,
    ): void {
        $query = DB::table('cashflow_audit_logs')
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->where('action', $action);

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        $count = $query->count();

        Assert::assertSame(
            1,
            $count,
            "Expected exactly 1 cashflow_audit_logs row for {$entityType}#{$entityId} action={$action}, "
            . "found {$count}. Either the observer was not invoked or it wrote a different action label."
        );
    }

    /**
     * Assert that NO row exists in `cashflow_audit_logs` for the entity.
     * Used to confirm a rolled-back transaction left no audit trace.
     */
    protected function assertNoCashflowAuditFor(string $entityType, int $entityId): void
    {
        $count = DB::table('cashflow_audit_logs')
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->count();

        Assert::assertSame(
            0,
            $count,
            "Expected no cashflow_audit_logs rows for {$entityType}#{$entityId}, found {$count}. "
            . 'A rolled-back transaction should leave NO audit trace.'
        );
    }

    /**
     * Assert that an `audit_trails` row exists for the given table+record.
     * Used for the legacy audit trail (non-CashFlow domain models).
     */
    protected function assertAuditTrailLogged(string $table, int $recordId, string $action = 'create'): void
    {
        $count = DB::table('audit_trails')
            ->where('table_name', $table)
            ->where('record_id', $recordId)
            ->where('action', $action)
            ->count();

        Assert::assertGreaterThanOrEqual(
            1,
            $count,
            "Expected at least 1 audit_trails row for {$table}#{$recordId} action={$action}, found {$count}."
        );
    }
}
