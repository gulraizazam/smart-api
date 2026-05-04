<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Bundles;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Deactivate packages whose end date has passed.
 *
 * Naming note: the SPA UI label "Packages" maps to the `bundles`
 * database table — the literal `packages` table holds patient plans, a
 * different concept. This command operates on the table behind the
 * "Packages" page (where end dates are user-settable).
 * See `Api\PackagesController` for the same UI/DB inversion.
 */
final class ExpirePackages extends Command
{
    protected $signature = 'packages:expire';

    protected $description = 'Deactivate packages past their end date (runs nightly at 1 AM PKT)';

    public function handle(): int
    {
        try {
            $cutoff = Carbon::now()->subDay()->toDateString();

            $updated = Bundles::whereDate('end', '<=', $cutoff)
                ->where('active', 1)
                ->update(['active' => 0]);

            $this->info("Deactivated {$updated} expired package(s).");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('packages:expire failed: '.$e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $this->error('Failed to expire packages: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
