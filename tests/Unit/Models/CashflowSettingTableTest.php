<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\CashFlow\CashflowSetting;
use Tests\TestCase;

/**
 * Regression pin for the CashflowSetting table name.
 *
 * The model's $table was once set to the string 'cashflow.settings.manage'
 * (a permission-slug-looking value pasted into $table). Laravel reads dots
 * in a table name as database.table separators, so every query rendered as
 * `FROM \`cashflow\`.\`settings\`.\`manage\`` -> SQL syntax error. That broke
 * EVERY cashflow settings read (go-live date, approval thresholds, digest
 * recipients, ...), which in turn made PoolService::recalculatePoolBalances
 * throw and left pool balances stale/wrong. The actual table is
 * `cashflow_settings` (see 2026_03_05_100001_create_cashflow_settings_table).
 */
final class CashflowSettingTableTest extends TestCase
{
    public function test_model_points_at_the_cashflow_settings_table(): void
    {
        $this->assertSame('cashflow_settings', (new CashflowSetting())->getTable());
    }

    public function test_table_name_has_no_dot_separators(): void
    {
        // A dotted table name silently becomes a db.table.column reference.
        $this->assertStringNotContainsString('.', (new CashflowSetting())->getTable());
    }
}
