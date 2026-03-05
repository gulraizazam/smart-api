<?php

namespace Database\Seeders;

use App\Models\CashFlow\ExpenseCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

class CashflowCategoriesSeeder extends Seeder
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
        $existing = ExpenseCategory::where('account_id', $accountId)->count();
        if ($existing > 0) {
            $this->command->info("Categories already exist for account {$accountId}. Skipping.");
            return;
        }

        // Default categories per spec §10.1
        $categories = [
            ['name' => 'Rent', 'vendor_emphasis' => true, 'description' => 'Office/clinic rent payments'],
            ['name' => 'Utilities', 'vendor_emphasis' => true, 'description' => 'Electricity, gas, water, internet'],
            ['name' => 'Medical Supplies', 'vendor_emphasis' => true, 'description' => 'Consumables, disposables, medicines'],
            ['name' => 'Equipment Maintenance', 'vendor_emphasis' => true, 'description' => 'Machine servicing and repairs'],
            ['name' => 'Staff Salary Advance', 'vendor_emphasis' => false, 'description' => 'Salary advances to staff'],
            ['name' => 'Petty Cash', 'vendor_emphasis' => false, 'description' => 'Small day-to-day expenses'],
            ['name' => 'Office Supplies', 'vendor_emphasis' => true, 'description' => 'Stationery, printing, supplies'],
            ['name' => 'Marketing', 'vendor_emphasis' => true, 'description' => 'Advertising, promotions, campaigns'],
            ['name' => 'Travel & Transport', 'vendor_emphasis' => false, 'description' => 'Staff travel, fuel, courier'],
            ['name' => 'Cleaning & Janitorial', 'vendor_emphasis' => true, 'description' => 'Cleaning services and supplies'],
            ['name' => 'Food & Beverages', 'vendor_emphasis' => false, 'description' => 'Staff meals, tea, refreshments'],
            ['name' => 'Professional Services', 'vendor_emphasis' => true, 'description' => 'Legal, accounting, consulting fees'],
            ['name' => 'Miscellaneous', 'vendor_emphasis' => false, 'description' => 'Other uncategorized expenses'],
        ];

        foreach ($categories as $index => $cat) {
            ExpenseCategory::create([
                'account_id' => $accountId,
                'name' => $cat['name'],
                'description' => $cat['description'],
                'vendor_emphasis' => $cat['vendor_emphasis'],
                'is_active' => 1,
                'sort_order' => $index + 1,
            ]);
        }

        $this->command->info("Seeded 13 default expense categories for account {$accountId}.");
    }
}
