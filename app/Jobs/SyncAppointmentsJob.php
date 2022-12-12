<?php

namespace App\Jobs;

use App\Models\Accounts;
use App\Models\Appointments;
use App\Models\HeavyLifter;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SyncAppointmentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Handle Current Account
     */
    protected $account;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Accounts $account)
    {
        $this->queue = 'high';
        $this->account = $account;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $total_records = Appointments::where([
            'account_id' => $this->account->id,
        ])->count();

        if($total_records) {
            $records_per_page = 1500;

            $total_calls = ceil($total_records / $records_per_page);

            if($total_calls) {

                $jobs = [];

                for($i = 0; $i < $total_calls; $i++) {
                    $offset = ($i * $records_per_page);

                    /**
                     * Payload
                     */
                    $payload = array(
                        'offset' => $offset,
                        'records_per_page' => $records_per_page,
                        'account_id' => $this->account->id,
                    );

                    $jobs[$i] = array(
                        'payload' => json_encode($payload),
                        'type' => 'upload-appointments',
                        'created_at' => Carbon::now()->toDateTimeString(),
                        'available_at' => Carbon::now()->toDateTimeString(),
                        'account_id' => $this->account->id,
                    );
                }

                HeavyLifter::insert($jobs);
            }
        }
    }
}
