<?php

namespace App\Console\Commands;

use App\Models\Appointments;
use App\Models\Packages;
use App\Models\PackageService;
use App\Models\PackageAdvances;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillConvertedAt extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'appointments:backfill-converted-at';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill converted_at column for appointments based on package services and advances';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Starting backfill of converted_at column...');

        $startDate = '2025-12-01';
        $endDate = Carbon::now()->format('Y-m-d');

        $this->info("Fetching appointments from {$startDate} to {$endDate}");

        // Get converted status ID (status with is_converted = 1)
        $convertedStatus = DB::table('appointment_statuses')
            ->where('is_converted', 1)
            ->whereNull('deleted_at')
            ->first();

        if (!$convertedStatus) {
            $this->error('Converted status not found in appointment_statuses table');
            return Command::FAILURE;
        }

        $convertedStatusId = $convertedStatus->id;
        $this->info("Found converted status ID: {$convertedStatusId}");

        // Fetch appointments with type_id=1 (consultation), status_id=2 (arrived)
        $appointments = DB::table('appointments')
            ->where('appointment_type_id', 1)
            ->where('base_appointment_status_id', 2)
            ->whereBetween('scheduled_date', [$startDate, $endDate])
            ->whereNull('converted_at')
            ->whereNull('deleted_at')
            ->whereNotNull('arrived_at')
            ->select('id', 'arrived_at', 'patient_id')
            ->get();

        $this->info("Found {$appointments->count()} appointments to check");

        $updatedCount = 0;

        foreach ($appointments as $appointment) {
            $arrivedAt = $appointment->arrived_at;
            $patientId = $appointment->patient_id;
            $this->info("Checking appointment ID: {$appointment->id}, patient_id: {$patientId}, arrived_at: {$arrivedAt}");

            // Get all packages for this patient (not just for this appointment)
            $packages = DB::table('packages')
                ->where('patient_id', $patientId)
                ->whereNull('deleted_at')
                ->pluck('id')
                ->toArray();

            if (empty($packages)) {
                $this->warn("  - No packages found for patient {$patientId}");
                continue;
            }
            $this->info("  - Found " . count($packages) . " package(s) for patient");

            // Check if at least 1 service was added in any of patient's packages after arrival
            $serviceAfterArrival = DB::table('package_services')
                ->whereIn('package_id', $packages)
                ->where('created_at', '>', $arrivedAt)
                ->first();

            if (!$serviceAfterArrival) {
                $this->warn("  - No service found after arrival in any package");
                continue;
            }
            $this->info("  - Service found after arrival: {$serviceAfterArrival->created_at}");

            // Check if at least 1 "in" payment exists in any of patient's packages after arrival
            $paymentAfterArrival = DB::table('package_advances')
                ->whereIn('package_id', $packages)
                ->where('cash_flow', 'in')
                ->where('cash_amount','>',0)
                ->where('created_at', '>', $arrivedAt)
                ->whereNull('deleted_at')
                ->first();

            if (!$paymentAfterArrival) {
                $this->warn("  - No 'in' payment found after arrival in any package");
                continue;
            }
            $this->info("  - Payment found after arrival: {$paymentAfterArrival->created_at}");

            // Update converted_at and status with the payment created_at date
            DB::table('appointments')
                ->where('id', $appointment->id)
                ->update([
                    'converted_at' => $paymentAfterArrival->created_at,
                    'base_appointment_status_id' => $convertedStatusId,
                    'appointment_status_id' => $convertedStatusId
                ]);

            $this->info("Updated appointment ID: {$appointment->id}");

            $updatedCount++;
        }

        $this->info("Updated {$updatedCount} appointments with converted_at.");

        return Command::SUCCESS;
    }
}
