<?php

namespace App\Console\Commands;

use App\Models\Patients;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanupInactivePatients extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'patients:cleanup-inactive 
                            {--dry-run : Run without actually deleting records}
                            {--batch-size=1000 : Number of records to process per batch}
                            {--year= : Only delete patients created in a specific year}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Soft-delete patients who have no records in appointments, packages, and package_advances tables';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $batchSize = (int) $this->option('batch-size');
        $year = $this->option('year');

        $this->info('=== Inactive Patients Cleanup Script ===');
        $this->newLine();

        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No records will be deleted');
            $this->newLine();
        }

        // Build the query for inactive patients
        $query = $this->buildInactivePatientsQuery();

        // Filter by year if specified
        if ($year) {
            $query->whereYear('created_at', $year);
            $this->info("Filtering patients created in year: {$year}");
        }

        // Get total count
        $totalCount = $query->count();

        if ($totalCount === 0) {
            $this->info('No inactive patients found matching the criteria.');
            return Command::SUCCESS;
        }

        $this->info("Found {$totalCount} inactive patients to process.");
        $this->newLine();

        // Show breakdown by year
        $this->showYearBreakdown($year);

        if (!$isDryRun) {
            if (!$this->confirm("Are you sure you want to soft-delete {$totalCount} patient records?")) {
                $this->info('Operation cancelled.');
                return Command::SUCCESS;
            }
        }

        // Process in batches
        $deletedCount = 0;
        $errorCount = 0;
        $progressBar = $this->output->createProgressBar($totalCount);
        $progressBar->start();

        // Get patient IDs in batches
        $query = $this->buildInactivePatientsQuery();
        if ($year) {
            $query->whereYear('created_at', $year);
        }

        $query->select('id')->orderBy('id')->chunk($batchSize, function ($patients) use ($isDryRun, &$deletedCount, &$errorCount, $progressBar) {
            $ids = $patients->pluck('id')->toArray();

            if (!$isDryRun) {
                try {
                    DB::beginTransaction();

                    // Soft delete using the deleted_at column
                    $affected = Patients::whereIn('id', $ids)
                        ->update(['deleted_at' => now()]);

                    DB::commit();

                    $deletedCount += $affected;

                    // Log the deletion
                    Log::info('Inactive patients cleanup', [
                        'action' => 'soft_delete',
                        'count' => $affected,
                        'patient_ids' => $ids,
                    ]);

                } catch (\Exception $e) {
                    DB::rollBack();
                    $errorCount += count($ids);
                    Log::error('Inactive patients cleanup failed', [
                        'error' => $e->getMessage(),
                        'patient_ids' => $ids,
                    ]);
                    $this->error("Error processing batch: " . $e->getMessage());
                }
            } else {
                $deletedCount += count($ids);
            }

            $progressBar->advance(count($ids));
        });

        $progressBar->finish();
        $this->newLine(2);

        // Summary
        $this->info('=== Cleanup Summary ===');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Found', $totalCount],
                [$isDryRun ? 'Would Delete' : 'Deleted', $deletedCount],
                ['Errors', $errorCount],
            ]
        );

        if ($isDryRun) {
            $this->newLine();
            $this->warn('This was a DRY RUN. Run without --dry-run to actually delete records.');
            $this->info('Example: php artisan patients:cleanup-inactive');
        }

        return Command::SUCCESS;
    }

    /**
     * Build the query for inactive patients
     */
    private function buildInactivePatientsQuery()
    {
        return Patients::where('user_type_id', 3)
            ->whereNull('deleted_at')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('appointments')
                    ->whereColumn('appointments.patient_id', 'users.id');
            })
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('packages')
                    ->whereColumn('packages.patient_id', 'users.id');
            })
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('package_advances')
                    ->whereColumn('package_advances.patient_id', 'users.id');
            });
    }

    /**
     * Show breakdown by year
     */
    private function showYearBreakdown(?string $filterYear = null)
    {
        $query = $this->buildInactivePatientsQuery();

        if ($filterYear) {
            $query->whereYear('created_at', $filterYear);
        }

        $byYear = $query->selectRaw('YEAR(created_at) as year, COUNT(*) as count')
            ->groupBy('year')
            ->orderBy('year')
            ->get();

        $this->info('Breakdown by Year:');
        $tableData = $byYear->map(function ($row) {
            return [$row->year ?? 'NULL', number_format($row->count)];
        })->toArray();

        $this->table(['Year', 'Count'], $tableData);
        $this->newLine();
    }
}
