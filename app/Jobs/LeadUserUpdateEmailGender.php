<?php

declare(strict_types=1);
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

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        protected readonly mixed $payload,
    ) {
        $this->queue = 'medium';
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        try {

            $user_info = User::find($this->payload['user_id']);
            $user_info->update(['email' => $this->payload['email'], 'gender' => $this->payload['gender']]);

        } catch (\Exception $exception) {
            \Illuminate\Support\Facades\Log::error('LeadUserUpdateEmailGender failed', [
                'user_id' => $this->payload['user_id'] ?? null,
                'line' => $exception->getLine(),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
            ]);
        }
    }
}
