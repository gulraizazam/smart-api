<?php

namespace App\Jobs;

use App\Mail\CashflowDailyDigest;
use App\Models\CashFlow\Expense;
use App\Models\CashFlow\CashflowSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendCashflowDailyDigest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        try {
            // Get all accounts that have cashflow configured
            $accounts = CashflowSetting::where('key', 'go_live_date')
                ->whereNotNull('value')
                ->pluck('account_id')
                ->unique();

            foreach ($accounts as $accountId) {
                $this->sendDigestForAccount($accountId);
            }
        } catch (\Exception $e) {
            Log::error('CashflowDailyDigest failed: ' . $e->getMessage());
        }
    }

    private function sendDigestForAccount(int $accountId): void
    {
        $voidAlertDays = (int) CashflowSetting::getValue('void_alert_days', $accountId, 7);

        // Flagged entries (recent)
        $flagged = Expense::where('account_id', $accountId)
            ->where('is_flagged', true)
            ->whereNull('voided_at')
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->select('expense_date', 'amount', 'flag_reason')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->toArray();

        // Pending approvals with age
        $pending = Expense::where('account_id', $accountId)
            ->where('status', 'pending')
            ->whereNull('voided_at')
            ->select('expense_date', 'amount', 'description', 'created_at')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(function ($e) {
                $e->age_days = Carbon::parse($e->created_at)->diffInDays(now());
                return $e;
            })
            ->toArray();

        // Rejected entries
        $rejected = Expense::where('account_id', $accountId)
            ->where('status', 'rejected')
            ->whereNull('voided_at')
            ->where('updated_at', '>=', Carbon::now()->subDays(7))
            ->select('expense_date', 'amount', 'rejection_reason')
            ->orderByDesc('updated_at')
            ->limit(20)
            ->get()
            ->toArray();

        // Admin self-approved
        $selfApproved = Expense::where('account_id', $accountId)
            ->where('status', 'approved')
            ->whereColumn('created_by', 'verified_by')
            ->whereNotNull('verified_by')
            ->whereNull('voided_at')
            ->where('updated_at', '>=', Carbon::now()->subDays(7))
            ->select('expense_date', 'amount', 'description')
            ->orderByDesc('updated_at')
            ->limit(20)
            ->get()
            ->toArray();

        // Voided entries (within alert window)
        $voided = Expense::where('account_id', $accountId)
            ->whereNotNull('voided_at')
            ->where('voided_at', '>=', Carbon::now()->subDays($voidAlertDays))
            ->select('expense_date', 'amount', 'void_reason')
            ->orderByDesc('voided_at')
            ->limit(20)
            ->get()
            ->toArray();

        // Skip if nothing to report
        if (empty($flagged) && empty($pending) && empty($rejected) && empty($selfApproved) && empty($voided)) {
            return;
        }

        $digestData = [
            'flagged_entries' => $flagged,
            'pending_approvals' => $pending,
            'rejected_entries' => $rejected,
            'self_approved' => $selfApproved,
            'voided_entries' => $voided,
        ];

        // Get recipients
        $recipients = CashflowSetting::getValue('digest_recipients', $accountId, '');
        if (empty($recipients)) {
            // Default: all admin users for this account
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
                Mail::to($email)->send(new CashflowDailyDigest($digestData));
            }
        }
    }
}
