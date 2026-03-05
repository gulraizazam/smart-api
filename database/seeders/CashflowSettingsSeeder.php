<?php

namespace Database\Seeders;

use App\Models\CashFlow\CashflowSetting;
use Illuminate\Database\Seeder;

class CashflowSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedForAllAccounts();
    }

    public function seedForAllAccounts(): void
    {
        $accountIds = \App\Models\User::distinct()->pluck('account_id')->filter();
        foreach ($accountIds as $accountId) {
            $this->seedForAccount((int) $accountId);
        }
    }

    public function seedForAccount(int $accountId): void
    {
        $existing = CashflowSetting::where('account_id', $accountId)->count();
        if ($existing > 0) {
            $this->command->info("Settings already exist for account {$accountId}. Skipping.");
            return;
        }

        // Default settings per spec §25
        $settings = [
            ['key' => 'go_live_date', 'value' => null, 'description' => 'Module go-live date. Patient inflows only counted from this date.'],
            ['key' => 'approval_threshold', 'value' => '10000', 'description' => 'Expense amount above which admin approval is required (PKR).'],
            ['key' => 'backdate_flag_days', 'value' => '7', 'description' => 'Days after which a backdated expense is auto-flagged.'],
            ['key' => 'daily_auto_approved_limit', 'value' => '50000', 'description' => 'Daily total auto-approved limit. Exceeding triggers splitting flag (PKR).'],
            ['key' => 'advance_aging_days', 'value' => '15', 'description' => 'Days after which an uncleared staff advance is flagged as aging.'],
            ['key' => 'cumulative_advance_threshold', 'value' => '100000', 'description' => 'Max cumulative advance balance before warning (PKR).'],
            ['key' => 'dormant_vendor_days', 'value' => '90', 'description' => 'Days of inactivity after which a vendor is considered dormant.'],
            ['key' => 'void_alert_days', 'value' => '7', 'description' => 'Days after approval within which void triggers extra alert.'],
            ['key' => 'digest_send_time', 'value' => '08:00', 'description' => 'Time to send daily digest email (HH:MM format).'],
            ['key' => 'digest_recipients', 'value' => '', 'description' => 'Comma-separated email addresses for daily digest.'],
        ];

        foreach ($settings as $setting) {
            CashflowSetting::create([
                'account_id' => $accountId,
                'key' => $setting['key'],
                'value' => $setting['value'],
                'description' => $setting['description'],
            ]);
        }

        $this->command->info("Seeded 10 default cashflow settings for account {$accountId}.");
    }
}
