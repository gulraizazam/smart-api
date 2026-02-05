<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Discounts;
use App\Models\DiscountHasLocations;

class MigrateDiscountDataToAllocations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'discounts:migrate-to-allocations {--dry-run : Show what would be updated without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate existing discount type/amount/slug data to allocation records';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        
        if ($dryRun) {
            $this->info('Running in DRY-RUN mode - no changes will be made.');
        }

        // Get all discounts that have type and amount set
        $discounts = Discounts::whereNotNull('type')
            ->whereNotNull('amount')
            ->withTrashed()
            ->get();

        $this->info("Found {$discounts->count()} discounts with type/amount data.");

        $updatedCount = 0;
        $skippedCount = 0;

        foreach ($discounts as $discount) {
            // Get all allocations for this discount that don't have type/amount set
            $allocations = DiscountHasLocations::where('discount_id', $discount->id)
                ->where(function ($query) {
                    $query->whereNull('type')
                        ->orWhereNull('amount');
                })
                ->get();

            if ($allocations->isEmpty()) {
                $this->line("  Discount #{$discount->id} ({$discount->name}): No allocations need updating.");
                $skippedCount++;
                continue;
            }

            $this->info("  Discount #{$discount->id} ({$discount->name}):");
            $this->line("    Type: {$discount->type}, Amount: {$discount->amount}, Slug: {$discount->slug}");
            $this->line("    Allocations to update: {$allocations->count()}");

            if (!$dryRun) {
                foreach ($allocations as $allocation) {
                    $allocation->update([
                        'type' => $allocation->type ?? $discount->type,
                        'amount' => $allocation->amount ?? $discount->amount,
                        'slug' => $allocation->slug ?? $discount->slug ?? 'default',
                    ]);
                }
                $this->info("    ✓ Updated {$allocations->count()} allocations.");
            } else {
                $this->warn("    [DRY-RUN] Would update {$allocations->count()} allocations.");
            }

            $updatedCount += $allocations->count();
        }

        $this->newLine();
        $this->info('=== Summary ===');
        $this->line("Total discounts processed: {$discounts->count()}");
        $this->line("Discounts skipped (no allocations to update): {$skippedCount}");
        $this->line("Total allocations " . ($dryRun ? "would be " : "") . "updated: {$updatedCount}");

        if ($dryRun) {
            $this->newLine();
            $this->warn('This was a dry run. Run without --dry-run to apply changes.');
        }

        return Command::SUCCESS;
    }
}
