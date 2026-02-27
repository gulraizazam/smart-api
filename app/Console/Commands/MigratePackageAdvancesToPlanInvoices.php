<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MigratePackageAdvancesToPlanInvoices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoices:migrate-advances 
                            {--from= : Start date (Y-m-d format, e.g., 2025-12-01)}
                            {--to= : End date (Y-m-d format, e.g., 2025-12-10)}
                            {--dry-run : Run without actually inserting records}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate package_advances records to plan_invoices table';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $fromDate = $this->option('from') ?? '2021-07-01';
        $toDate = $this->option('to') ?? '2025-12-31';
        $isDryRun = $this->option('dry-run');

        $this->info("===========================================");
        $this->info("  Migrate Package Advances to Plan Invoices");
        $this->info("===========================================");
        $this->info("Date Range: {$fromDate} to {$toDate}");
        $this->info("Mode: " . ($isDryRun ? "DRY RUN (no changes will be made)" : "LIVE"));
        $this->newLine();

        // Fetch package_advances records
        $advances = DB::table('package_advances')
            ->where('cash_flow', 'in')
            ->where('cash_amount', '>', 0)
            ->where('location_id',55)
            ->whereNull('deleted_at')
            ->whereBetween('created_at', [
                Carbon::parse($fromDate)->startOfDay(),
                Carbon::parse($toDate)->endOfDay()
            ])
            ->orderBy('patient_id')
            ->orderBy('package_id')
            ->orderBy('created_at')
            ->get();

        if ($advances->isEmpty()) {
            $this->warn("No records found in package_advances for the given date range.");
            return Command::SUCCESS;
        }

        $this->info("Found {$advances->count()} records to migrate.");
        $this->newLine();

        if (!$isDryRun && !$this->confirm('Do you want to proceed with migration?')) {
            $this->info("Migration cancelled.");
            return Command::SUCCESS;
        }

        // Group by patient_id and package_id to generate sequential invoice numbers
        $invoiceCounters = [];
        $invoicesToInsert = [];
        $successCount = 0;
        $errorCount = 0;

        $progressBar = $this->output->createProgressBar($advances->count());
        $progressBar->start();

        foreach ($advances as $advance) {
            try {
                // Generate invoice number: patient_id-package_id-sequence
                $key = "{$advance->patient_id}-{$advance->package_id}";
                
                if (!isset($invoiceCounters[$key])) {
                    // Get the maximum sequence number from existing invoices for this patient-package combination
                    $lastInvoice = DB::table('plan_invoices')
                        ->where('patient_id', $advance->patient_id)
                        ->where('package_id', $advance->package_id)
                        ->orderByRaw('CAST(SUBSTRING_INDEX(invoice_number, "-", -1) AS UNSIGNED) DESC')
                        ->value('invoice_number');
                    
                    if ($lastInvoice) {
                        // Extract the sequence number from the last invoice (e.g., "174043-33876-03" -> 3)
                        $lastSequence = (int) substr($lastInvoice, strrpos($lastInvoice, '-') + 1);
                        $invoiceCounters[$key] = $lastSequence;
                    } else {
                        $invoiceCounters[$key] = 0;
                    }
                }
                
                $invoiceCounters[$key]++;
                $sequenceNumber = str_pad($invoiceCounters[$key], 2, '0', STR_PAD_LEFT);
                $invoiceNumber = "{$advance->patient_id}-{$advance->package_id}-{$sequenceNumber}";

                $invoiceData = [
                    'invoice_number' => $invoiceNumber,
                    'total_price' => $advance->cash_amount,
                    'account_id' => $advance->account_id ?? 1, // Default account if null
                    'patient_id' => $advance->patient_id,
                    
                    'created_by' => $advance->created_by ?? 1,
                    'location_id' => $advance->location_id ?? 1,
                  
                    'payment_mode_id' => $advance->payment_mode_id,
                    'active' => 1,
                   
                    'package_id' => $advance->package_id,
                    'package_advance_id' => $advance->id,
                    'invoice_type' => 'exempt', // Default type
                   
                    'created_at' => $advance->created_at,
                    'updated_at' => now(),
                    'deleted_at' => null,
                ];

                $invoicesToInsert[] = $invoiceData;
                $successCount++;

            } catch (\Exception $e) {
                $errorCount++;
                $this->newLine();
                $this->error("Error processing advance ID {$advance->id}: {$e->getMessage()}");
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Insert records in batches
        if (!$isDryRun && !empty($invoicesToInsert)) {
            $this->info("Inserting records into plan_invoices...");
            
            $chunks = array_chunk($invoicesToInsert, 500);
            $insertProgressBar = $this->output->createProgressBar(count($chunks));
            $insertProgressBar->start();

            foreach ($chunks as $chunk) {
                DB::table('plan_invoices')->insert($chunk);
                $insertProgressBar->advance();
            }

            $insertProgressBar->finish();
            $this->newLine(2);
        }

        // Summary
        $this->info("===========================================");
        $this->info("  Migration Summary");
        $this->info("===========================================");
        $this->info("Total Records Processed: {$advances->count()}");
        $this->info("Successfully Prepared: {$successCount}");
        $this->info("Errors: {$errorCount}");
        
        if ($isDryRun) {
            $this->warn("DRY RUN - No records were actually inserted.");
            $this->newLine();
            $this->info("Sample invoice numbers that would be generated:");
            $sampleInvoices = array_slice($invoicesToInsert, 0, 10);
            foreach ($sampleInvoices as $invoice) {
                $this->line("  - {$invoice['invoice_number']} | Amount: {$invoice['total_price']} | Patient: {$invoice['patient_id']} | Date: {$invoice['created_at']}");
            }
        } else {
            $this->info("Records Inserted: {$successCount}");
        }

        return Command::SUCCESS;
    }
}