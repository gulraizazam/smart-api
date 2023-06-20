<?php

namespace App\Events;

use App\Models\Appointments;
use App\Models\AuditTrails;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\SerializesModels;

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
     * @param  Request  $request
     */
    public function created(Appointments $appointment)
    {
        AuditTrails::addEventLogger($appointment->__table, 'create', $appointment->toArray(), $appointment->__fillable, $appointment);

    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return void
     */
    public function updating(Appointments $appointment)
    {
        $old_data = (Appointments::find($appointment->id))->toArray();
        AuditTrails::editEventLogger($appointment->__table, 'Edit', $appointment->toArray(), $appointment->__fillable, $old_data, $appointment->id);
    }

    /**
     * @return void
     */
    public function deleting(Appointments $appointment)
    {
        AuditTrails::deleteEventLogger($appointment->__table, 'delete', $appointment->__fillable, $appointment->id);

    }
}
