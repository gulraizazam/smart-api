<?php

namespace App\Jobs;

use App\Helpers\Elastic\AppointmentsElastic;
use App\Models\Appointments;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncSingleAppointmentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Holds payload data
     */
    protected $payload;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($payload)
    {
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
