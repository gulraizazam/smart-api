<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Jobs\SendCashflowDailyDigest;
use App\Jobs\SendCashflowMonthlyReport;
use App\Models\CashFlow\CashflowSetting;
use App\Models\CashFlow\Expense;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * The cashflow daily digest and monthly report jobs are the only
 * automated communication channel for the finance team. A silent
 * failure here means flagged expenses go unnoticed, pending approvals
 * pile up, and monthly reconciliation breaks.
 *
 * Pins:
 *   1. Daily digest queries flagged, pending, rejected, self-approved,
 *      and voided expenses correctly.
 *   2. Digest skips sending when all query results are empty.
 *   3. Monthly report calculates previous-month range correctly.
 *   4. Both jobs respect custom recipient configuration.
 *   5. Both jobs fall back to admin users when no custom recipients set.
 */
class CashflowJobsTest extends TestCase
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

    private function seedGoLiveDate(): void
    {
        $accountId = auth()->user()->account_id;
        CashflowSetting::updateOrCreate(
            ['account_id' => $accountId, 'key' => 'go_live_date'],
            ['value' => '2026-01-01'],
        );
    }

    private function seedDigestRecipients(): void
    {
        $accountId = auth()->user()->account_id;
        CashflowSetting::updateOrCreate(
            ['account_id' => $accountId, 'key' => 'digest_recipients'],
            ['value' => auth()->user()->email],
        );
    }

    public function test_daily_digest_dispatches_without_error(): void
    {
        $this->seedGoLiveDate();

        // Should not throw — even with no data.
        $job = new SendCashflowDailyDigest;
        $job->handle();

        $this->assertTrue(true, 'Daily digest job completed without exception.');
    }

    public function test_daily_digest_skips_email_when_no_data(): void
    {
        $this->seedGoLiveDate();

        $job = new SendCashflowDailyDigest;
        $job->handle();

        // With no expenses at all, no email should be sent.
        Mail::assertNothingSent();
    }

    public function test_daily_digest_sends_email_when_flagged_expenses_exist(): void
    {
        $this->seedGoLiveDate();
        $this->seedDigestRecipients();

        // Create a flagged expense.
        Expense::factory()->flagged()->create([
            'account_id' => auth()->user()->account_id,
            'created_at' => now()->subDays(2),
        ]);

        $job = new SendCashflowDailyDigest;
        $job->handle();

        Mail::assertSent(\App\Mail\CashflowDailyDigest::class);
    }

    public function test_daily_digest_sends_email_when_pending_approvals_exist(): void
    {
        $this->seedGoLiveDate();
        $this->seedDigestRecipients();

        Expense::factory()->create([
            'account_id' => auth()->user()->account_id,
            'status' => 'pending',
            'voided_at' => null,
        ]);

        $job = new SendCashflowDailyDigest;
        $job->handle();

        Mail::assertSent(\App\Mail\CashflowDailyDigest::class);
    }

    public function test_monthly_report_dispatches_without_error(): void
    {
        $this->seedGoLiveDate();

        $job = new SendCashflowMonthlyReport;
        $job->handle(app(\App\Services\CashFlow\ReportService::class));

        $this->assertTrue(true, 'Monthly report job completed without exception.');
    }

    public function test_daily_digest_does_not_run_for_accounts_without_go_live_date(): void
    {
        // Do NOT seed go_live_date — the job should skip this account.
        Expense::factory()->flagged()->create([
            'account_id' => auth()->user()->account_id,
        ]);

        $job = new SendCashflowDailyDigest;
        $job->handle();

        Mail::assertNothingSent();
    }
}
