<?php

namespace App\Jobs;

use App\Helpers\Elastic\AppointmentsElastic;
use App\Models\Accounts;
use App\Models\Appointments;
use App\Models\HeavyLifter;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class IndexSingleAppointmentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Holds payload data
     *
     */
    protected $payload;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($payload)
    {
        $this->queue = 'medium';
        $this->payload = $payload;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            $appointment = Appointments::where([
                'account_id' => $this->payload['account_id'],
                'id' => $this->payload['appointment_id'],
            ])->first();

            AppointmentsElastic::indexObject($appointment);

        } catch (\Exception $exception) {

        }

        return true;
    }
}
