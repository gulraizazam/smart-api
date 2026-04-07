<?php

declare(strict_types=1);
namespace App\Events;

use App\Models\Accounts;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SyncAppointmentsFire
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Variable holding account object
     */
    protected Accounts $account;

    public function __construct(Accounts $account)
    {
        $this->account = $account;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn(): Channel|array
    {
        return new PrivateChannel('channel-name');
    }
}
