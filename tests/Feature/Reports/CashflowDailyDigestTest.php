<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Jobs\SendCashflowDailyDigest;
use App\Mail\CashflowDailyDigest;
use App\Models\CashFlow\Expense;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Smoke coverage for the cashflow daily digest job — the scheduled
 * task that emails admins a rollup of flagged / pending / rejected /
 * self-approved / voided expenses for each account with cashflow
 * enabled.
 *
 * Pins:
 *   1. With no accounts having cashflow enabled (no `go_live_date`
 *      setting), the job exits without dispatching any mail.
 *   2. With a go_live_date set but no expenses to report, the job
 *      still dispatches nothing (the "empty digest" short-circuit
 *      inside `sendDigestForAccount()`).
 *   3. With a flagged expense, the digest mail is queued.
 */
class CashflowDailyDigestTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();

        Mail::fake();
    }

    public function test_job_dispatches_nothing_when_no_account_has_go_live_date(): void
    {
        (new SendCashflowDailyDigest)->handle();

        Mail::assertNothingSent();
    }

    public function test_job_dispatches_nothing_when_digest_is_empty_for_account(): void
    {
        DB::table('cashflow_settings')->insert([
            'account_id' => 1,
            'key' => 'go_live_date',
            'value' => now()->format('Y-m-d'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        (new SendCashflowDailyDigest)->handle();

        // No flagged, pending, rejected, self-approved, or voided
        // expenses to send — the job's "skip if nothing to report"
        // branch should fire.
        Mail::assertNothingSent();
    }

    public function test_job_dispatches_mail_when_flagged_expense_exists(): void
    {
        // Seed go_live_date and a valid set of digest recipients so
        // the job targets a concrete email address rather than a
        // computed admin user list.
        DB::table('cashflow_settings')->insert([
            ['account_id' => 1, 'key' => 'go_live_date', 'value' => now()->format('Y-m-d'), 'created_at' => now(), 'updated_at' => now()],
            ['account_id' => 1, 'key' => 'digest_recipients', 'value' => 'finance@example.com', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // A flagged expense within the default 7-day window.
        Expense::factory()->create([
            'account_id' => 1,
            'is_flagged' => 1,
            'flag_reason' => 'Over policy limit',
            'created_at' => now()->subHours(2),
            'voided_at' => null,
        ]);

        (new SendCashflowDailyDigest)->handle();

        Mail::assertSent(CashflowDailyDigest::class);
    }
}
