<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class LeadUserUpdateEmailGender implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $payload;

    /**
     * Create a new job instance.
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

            $user_info = User::find($this->payload['user_id']);
            $user_info->update(['email' => $this->payload['email'], 'gender' => $this->payload['gender']]);

            return true;

        } catch (\Exception $exception) {
            $exception->getLine().'---'.$exception->getMessage().'----'.$exception->getFile();
        }
    }
}
