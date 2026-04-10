<?php

declare(strict_types=1);

namespace Tests\Feature\CashFlow;

use App\Models\CashFlow\CashflowAuditLog;
use Illuminate\Database\QueryException;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * The cashflow_audit_logs table is the immutable record of every state
 * transition on the financial spine. The audit found that the table had no
 * UPDATE/DELETE triggers and could be silently rewritten — the fix added a
 * MariaDB trigger that raises a SIGNAL on UPDATE/DELETE.
 *
 * These tests assert that the trigger is in place and that any attempt to
 * mutate or remove an audit row throws.
 */
class CashflowAuditLogImmutabilityTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
    }

    public function test_an_audit_row_can_be_inserted_via_eloquent(): void
    {
        $log = CashflowAuditLog::create([
            'account_id' => 1,
            'user_id' => auth()->id(),
            'action' => 'created',
            'entity_type' => 'expense',
            'entity_id' => 1,
            'old_values' => null,
            'new_values' => json_encode(['amount' => 100]),
            'ip_address' => '127.0.0.1',
            'created_at' => now(),
        ]);

        $this->assertNotNull($log->id);
    }

    public function test_updating_an_audit_row_is_rejected_by_the_database(): void
    {
        $id = DB::table('cashflow_audit_logs')->insertGetId([
            'account_id' => 1,
            'user_id' => auth()->id(),
            'action' => 'created',
            'entity_type' => 'expense',
            'entity_id' => 1,
            'new_values' => json_encode(['amount' => 100]),
            'created_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        DB::table('cashflow_audit_logs')->where('id', $id)->update(['action' => 'tampered']);
    }

    public function test_deleting_an_audit_row_is_rejected_by_the_database(): void
    {
        $id = DB::table('cashflow_audit_logs')->insertGetId([
            'account_id' => 1,
            'user_id' => auth()->id(),
            'action' => 'created',
            'entity_type' => 'expense',
            'entity_id' => 1,
            'new_values' => json_encode(['amount' => 100]),
            'created_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        DB::table('cashflow_audit_logs')->where('id', $id)->delete();
    }
}
