<?php

namespace App\Console\Commands;

use App\Models\Appointments;
use App\Models\Invoices;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillArrivedAt extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'appointments:backfill-arrived-at';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill arrived_at column for appointments based on invoice creation date';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Starting backfill of arrived_at column...');

        $startDate = '2025-12-01';
        $endDate = Carbon::now()->format('Y-m-d');

        $this->info("Fetching appointments from {$startDate} to {$endDate}");

        // Fetch appointments with type_id=1 (consultation), status_id=2 (arrived)
        // Join with invoices table and update arrived_at with invoice created_at
        $updated = DB::table('appointments')
            ->join('invoices', 'invoices.appointment_id', '=', 'appointments.id')
            ->where('appointments.appointment_type_id', 1)
            ->where('appointments.base_appointment_status_id', 2)
            ->whereBetween('appointments.scheduled_date', [$startDate, $endDate])
            ->whereNull('appointments.arrived_at')
            ->whereNull('appointments.deleted_at')
            ->whereNull('invoices.deleted_at')
            ->update([
                'appointments.arrived_at' => DB::raw('invoices.created_at')
            ]);

        $this->info("Updated {$updated} appointments with arrived_at from invoice creation date.");

        return Command::SUCCESS;
    }
}
