<?php

declare(strict_types=1);
namespace App\Console\Commands;

use App\Jobs\SyncSingleAppointmentJob;
use App\Models\Appointments;
use App\Models\HeavyLifter;
use Illuminate\Console\Command;
use Queue;

class HandleHeavyLifting extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cms:handle-heavy-lifting';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Handle large requests divided into chunks';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    #[\Override]
    public function handle(): int
    {
        try {

            $heavyLifts = HeavyLifter::limit(5)
                ->get();

            if ($heavyLifts) {

                /**
                 * Variable holding shopify jobs
                 */
                $shopify_jobs = [];

                foreach ($heavyLifts as $heavyLift) {

                    $payload = json_decode($heavyLift->payload, true);

                    switch ($heavyLift->type) {
                        case 'upload-appointments':

                            $appointments = Appointments::where([
                                'account_id' => $payload['account_id'],
                            ])
                                ->limit($payload['records_per_page'])
                                ->offset($payload['offset'])
                                ->orderBy('id', 'asc')
                                ->select('id')
                                ->get();

                            if ($appointments) {

                                $jobs_array = [];

                                foreach ($appointments as $appointment) {
                                    $payload = [
                                        'account_id' => $payload['account_id'],
                                        'appointment_id' => $appointment->id,
                                    ];

                                    $jobs_array[] = new SyncSingleAppointmentJob($payload);
                                }

                                Queue::bulk($jobs_array);
                            }

                            break;
                        default:
                            break;
                    }

                    HeavyLifter::where([
                        'id' => $heavyLift->id,
                    ])->forceDelete();
                }
            }

        } catch (\Exception $e) {
            \Log::error('HandleHeavyLifting exception', ['error' => $e->getMessage()]);
        }

        return Command::SUCCESS;
    }
}
