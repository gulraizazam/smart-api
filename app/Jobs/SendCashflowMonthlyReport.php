<?php

namespace App\Jobs;

use App\Mail\CashflowMonthlyReport;
use App\Models\CashFlow\CashflowSetting;
use App\Models\User;
use App\Services\CashFlow\ReportService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendCashflowMonthlyReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(ReportService $reportService): void
    {
        try {
            $accounts = CashflowSetting::where('key', 'go_live_date')
                ->whereNotNull('value')
                ->pluck('account_id')
                ->unique();

            foreach ($accounts as $accountId) {
                $this->sendReportForAccount($accountId, $reportService);
            }
        } catch (\Exception $e) {
            Log::error('CashflowMonthlyReport failed: ' . $e->getMessage());
        }
    }

    private function sendReportForAccount(int $accountId, ReportService $reportService): void
    {
        // Previous month
        $prevMonth = Carbon::now()->subMonth();
        $dateFrom = $prevMonth->startOfMonth()->toDateString();
        $dateTo = $prevMonth->endOfMonth()->toDateString();
        $monthLabel = $prevMonth->format('F Y');

        $statement = $reportService->cashFlowStatement($accountId, [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ]);

        $reportData = array_merge($statement, ['month_label' => $monthLabel]);

        // Get recipients
        $recipients = CashflowSetting::getValue('digest_recipients', $accountId, '');
        if (empty($recipients)) {
            $recipients = User::where('account_id', $accountId)
                ->where('active', 1)
                ->whereHas('roles', function ($q) { $q->where('name', 'admin'); })
                ->whereNotNull('email')
                ->pluck('email')
                ->toArray();
        } else {
            $recipients = array_map('trim', explode(',', $recipients));
        }

        foreach ($recipients as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Mail::to($email)->send(new CashflowMonthlyReport($reportData));
            }
        }
    }
}
