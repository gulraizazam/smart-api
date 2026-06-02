<?php

declare(strict_types=1);

namespace Tests\Feature\CashFlow;

use App\Models\CashFlow\CashflowSetting;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Strict tripwire for the CashflowSetting Eloquent model's table binding.
 *
 * Regression: `$table` was set to the dotted permission slug
 * `cashflow.settings.manage` (copy-paste from `Gate::allows('cashflow.settings.manage')`).
 * Laravel's grammar wraps each dot-delimited segment, producing the invalid
 * three-part identifier `` `cashflow`.`settings`.`manage` `` and a 1064 syntax
 * error on EVERY query through the model. It surfaced first as a 500 on the
 * FDM dashboard cash-flow section, but broke every cashflow path that reads
 * settings.
 *
 * The sibling dashboard endpoint tests tolerate 500 (`assertContains(..., [200,
 * 403, 500])`), so they never caught it. These assertions do NOT — they run a
 * real query and round-trip a row, which throws on a bad table name.
 *
 * @see \App\Models\CashFlow\CashflowSetting
 */
class CashflowSettingModelTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
    }

    public function test_table_resolves_to_the_real_cashflow_settings_table(): void
    {
        $this->assertSame('cashflow_settings', (new CashflowSetting)->getTable());
    }

    public function test_set_get_and_collect_round_trip_against_a_real_table(): void
    {
        $accountId = 1;

        // Each call issues a real query — a bad $table throws QueryException
        // here rather than silently returning a tolerated 500 upstream.
        CashflowSetting::setValue('void_alert_days', '7', $accountId);
        CashflowSetting::setValue('digest_send_time', '08:00', $accountId);

        $this->assertSame('7', CashflowSetting::getValue('void_alert_days', $accountId));
        $this->assertSame('fallback', CashflowSetting::getValue('missing_key', $accountId, 'fallback'));

        $all = CashflowSetting::getAllForAccount($accountId);
        // Order-independent: pluck() does not guarantee key order.
        $this->assertEqualsCanonicalizing(
            ['void_alert_days' => '7', 'digest_send_time' => '08:00'],
            $all,
        );
    }
}
