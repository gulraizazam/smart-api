<?php

declare(strict_types=1);

namespace Tests\Feature\CashFlow;

use App\Services\CashFlow\DashboardService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Pins that the cash-flow summary cards window on the PKT **business date**
 * (`created_at`), not `system_created_at` (the UTC system-insert time).
 *
 * Regression: the app runs on Asia/Karachi, so `created_at` is PKT while the
 * DB-default `system_created_at` is UTC (+5h behind). The summary previously
 * windowed on `system_created_at`, so a payment whose business date is the 1st
 * of this month but whose UTC insert time fell on the last day of the previous
 * month was mis-bucketed into the OPENING balance instead of this month's
 * inflows — and the ~midnight-05:00 slice shifted month/week boundaries.
 * Before/after deltas keep the assertion robust to any seeded fixtures.
 */
class CashflowSummaryTimezoneTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->seedDefaultCashPool();
        // "Now" = mid-June (PKT) → this month = June 2026, prev month ends 31 May.
        Carbon::setTestNow('2026-06-15 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_inflows_window_uses_pkt_business_date_not_utc_system_time(): void
    {
        $svc = app(DashboardService::class);
        $before = $svc->getSummaryCards(1, '2026-01-01');

        // Business date (created_at) = 1 June (this month); UTC system time = 31 May (prev month).
        DB::table('package_advances')->insert([
            'account_id' => 1,
            'cash_flow' => 'in',
            'is_cancel' => 0,
            'is_refund' => 0,
            'cash_amount' => 5000,
            'created_at' => '2026-06-01 02:00:00',        // PKT business date — June
            'system_created_at' => '2026-05-31 21:00:00', // UTC system time — May
            'updated_at' => '2026-06-01 02:00:00',
        ]);

        $after = $svc->getSummaryCards(1, '2026-01-01');

        // Windowed on created_at (PKT) → the payment lands in THIS month's inflows…
        $this->assertEqualsWithDelta(
            5000.0,
            $after['inflows'] - $before['inflows'],
            0.01,
            'Inflows must window on created_at (PKT business date), not system_created_at (UTC).',
        );
        // …and is NOT folded into the opening (previous-period) balance.
        $this->assertEqualsWithDelta(
            0.0,
            $after['opening_balance'] - $before['opening_balance'],
            0.01,
            'A June business-date payment must not be mis-bucketed into the opening balance.',
        );
    }
}
