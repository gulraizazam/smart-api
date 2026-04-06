<?php

declare(strict_types=1);
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BackfillMissingPackages extends Command
{
    protected $signature = 'packages:backfill-missing
                            {--dry-run : Preview orphaned package IDs without making changes}
                            {--delete : Delete orphaned child records instead of restoring packages}';

    protected $description = 'Restore deleted packages rows from package_advances/package_bundles/package_services data, or delete the orphaned child records';

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $doDelete = $this->option('delete');

        // Collect all package_ids referenced by child tables that have no matching
        // packages row (even considering soft-deleted rows).
        $orphanIds = $this->findOrphanPackageIds();

        if ($orphanIds->isEmpty()) {
            $this->info('No orphaned package IDs found. Nothing to do.');
            return Command::SUCCESS;
        }

        $this->info("Found {$orphanIds->count()} orphaned package ID(s): " . $orphanIds->implode(', '));

        if ($isDryRun) {
            $this->table(
                ['package_id', 'in package_advances', 'in package_bundles', 'in package_services'],
                $orphanIds->map(fn ($id) => [
                    $id,
                    DB::table('package_advances')->where('package_id', $id)->exists() ? 'yes' : 'no',
                    DB::table('package_bundles')->where('package_id', $id)->exists() ? 'yes' : 'no',
                    DB::table('package_services')->where('package_id', $id)->exists() ? 'yes' : 'no',
                ])
            );
            $this->info('Dry-run complete. No changes made.');
            return Command::SUCCESS;
        }

        if ($doDelete) {
            return $this->deleteOrphanChildRecords($orphanIds);
        }

        return $this->restorePackages($orphanIds);
    }

    /**
     * Return all package_ids referenced in child tables that have no row in packages
     * (including soft-deleted rows).
     */
    private function findOrphanPackageIds()
    {
        $fromAdvances  = DB::table('package_advances')
            ->whereNotNull('package_id')
            ->pluck('package_id');

        $fromBundles   = DB::table('package_bundles')
            ->whereNotNull('package_id')
            ->pluck('package_id');

        $fromServices  = DB::table('package_services')
            ->whereNotNull('package_id')
            ->pluck('package_id');

        $allChildIds = $fromAdvances
            ->merge($fromBundles)
            ->merge($fromServices)
            ->unique()
            ->values();

        // Check against packages table including soft-deleted rows
        $existingIds = DB::table('packages')
            ->whereIn('id', $allChildIds)
            ->pluck('id');

        return $allChildIds->diff($existingIds)->sort()->values();
    }

    /**
     * Reconstruct and insert a packages row for each orphaned ID.
     */
    private function restorePackages($orphanIds): int
    {
        $restored = 0;
        $skipped  = 0;

        foreach ($orphanIds as $packageId) {
            // Pull context from package_advances (most reliable source for patient/account/location)
            $advance = DB::table('package_advances')
                ->where('package_id', $packageId)
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->first();

            if (! $advance) {
                // Fall back to any advance row including soft-deleted
                $advance = DB::table('package_advances')
                    ->where('package_id', $packageId)
                    ->orderBy('id')
                    ->first();
            }

            // Pull totals from package_bundles
            $bundleAgg = DB::table('package_bundles')
                ->where('package_id', $packageId)
                ->whereNull('deleted_at')
                ->selectRaw('SUM(net_amount) as total_price, SUM(qty) as total_qty, MAX(is_exclusive) as is_exclusive, MAX(location_id) as location_id')
                ->first();

            $totalPrice  = $bundleAgg->total_price ?? 0;
            $sessionCount = (int) ($bundleAgg->total_qty ?? 0);
            $isExclusive = $bundleAgg->is_exclusive ?? null;
            $locationId  = $advance->location_id
                ?? $bundleAgg->location_id
                ?? null;

            $patientId   = $advance->patient_id  ?? null;
            $accountId   = $advance->account_id  ?? null;
            $createdAt   = $advance->created_at  ?? now();

            if (! $patientId && ! $accountId) {
                $this->warn("  Skipping package_id={$packageId}: could not determine patient_id or account_id.");
                $skipped++;
                continue;
            }

            $row = [
                'id'           => $packageId,
                'random_id'    => Str::uuid()->toString(),
                'name'         => sprintf('%05d', $packageId),
                'plan_name'    => null,
                'sessioncount' => $sessionCount,
                'total_price'  => $totalPrice,
                'total_price_bk' => $totalPrice,
                'is_refund'    => 0,
                'is_exclusive' => $isExclusive,
                'plan_type'    => 'plan',
                'account_id'   => $accountId,
                'patient_id'   => $patientId,
                'location_id'  => $locationId,
                'appointment_id' => $advance->appointment_id ?? null,
                'active'       => 1,
                'created_at'   => $createdAt,
                'updated_at'   => $createdAt,
                'deleted_at'   => null,
            ];

            DB::table('packages')->insert($row);

            $this->info("  Restored package_id={$packageId} (patient_id={$patientId}, account_id={$accountId}, total_price={$totalPrice})");
            $restored++;
        }

        $this->info("\nDone. Restored: {$restored}, Skipped: {$skipped}.");
        return Command::SUCCESS;
    }

    /**
     * Hard-delete child records that reference non-existent packages rows.
     */
    private function deleteOrphanChildRecords($orphanIds): int
    {
        if (! $this->confirm("This will PERMANENTLY delete all package_advances, package_bundles, and package_services rows for the {$orphanIds->count()} orphaned package IDs. Continue?")) {
            $this->info('Aborted.');
            return Command::SUCCESS;
        }

        $deletedAdvances  = DB::table('package_advances')->whereIn('package_id', $orphanIds)->delete();
        $deletedBundles   = DB::table('package_bundles')->whereIn('package_id', $orphanIds)->delete();
        $deletedServices  = DB::table('package_services')->whereIn('package_id', $orphanIds)->delete();

        $this->info("Deleted: {$deletedAdvances} package_advances, {$deletedBundles} package_bundles, {$deletedServices} package_services.");
        return Command::SUCCESS;
    }
}
