<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Discount;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

final class InactiveDiscounts extends Command
{
    protected $signature = 'discounts:inactive';

    protected $description = 'Deactivate expired discounts (runs nightly at 1 AM)';

    public function handle(): int
    {
        try {
            $yesterday = Carbon::now()->subDay()->toDateString();

            $updated = Discount::where('active', true)
                ->whereDate('end', '<=', $yesterday)
                ->update(['active' => false]);

            $this->info("Deactivated {$updated} expired discount(s).");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('InactiveDiscounts Command Error', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);

            $this->error('Failed to deactivate discounts: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
