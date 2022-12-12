<?php

namespace App\Events;

use App\Models\Appointments;
use App\Models\AuditTrails;
use Illuminate\Broadcasting\Channel;
use Illuminate\Http\Request;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Support\Facades\Log;

class AppointmentEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * @param Appointments $appointment
     * @param Request $request
     */
    public function created(Appointments $appointment)

    {
        AuditTrails::addEventLogger($appointment->__table, 'create', $appointment->toArray(), $appointment->__fillable, $appointment);

    }


    /**
     * Get the channels the event should broadcast on.
     *
     * @param Appointments $appointment
     * @return void
     */

    public function updating(Appointments $appointment)
    {
        $old_data = (Appointments::find($appointment->id))->toArray();
        AuditTrails::editEventLogger($appointment->__table, 'Edit', $appointment->toArray(), $appointment->__fillable, $old_data, $appointment->id);
    }


    /**
     *
     * @param Appointments $appointment
     * @return void
     */

    public function deleting(Appointments $appointment)

    {
        AuditTrails::deleteEventLogger($appointment->__table, 'delete', $appointment->__fillable, $appointment->id);

    }


}
