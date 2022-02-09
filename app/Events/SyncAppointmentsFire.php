<?php

namespace App\Events;

use App\Models\Accounts;
use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class SyncAppointmentsFire
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Variable holding account object
     */
    protected $account;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(Accounts $account)
    {
        $this->account = $account;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('channel-name');
    }
}
