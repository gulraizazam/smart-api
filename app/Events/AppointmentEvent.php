<?php

declare(strict_types=1);
namespace App\Events;

use App\Models\Appointments;
use App\Models\AuditTrails;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;

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
        // Audit rows require a non-null user_id (the column is NOT NULL).
        // Skip the append when no user is authenticated — e.g. a CLI
        // seeder or a test fixture that sets up state without an actor.
        // Losing an audit row here is safer than crashing the create.
        if (! Auth::check()) {
            return;
        }
        AuditTrails::addEventLogger($appointment->__table, 'create', $appointment->toArray(), $appointment->__fillable, $appointment);

    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return void
     */
    public function updating(Appointments $appointment)
    {
        if (! Auth::check()) {
            return;
        }
        // withTrashed() matters here: the `updating` event also fires on
        // restore() (an UPDATE that clears deleted_at), at which moment
        // the row is still soft-deleted so a plain find() returns null
        // and the AuditTrails diff walk then crashes on the empty
        // old_data array. Pull the pre-mutation snapshot through the
        // trashed scope so restore-driven updates also diff cleanly.
        $old_data = Appointments::withTrashed()->find($appointment->id)?->toArray() ?? [];
        AuditTrails::editEventLogger($appointment->__table, 'Edit', $appointment->toArray(), $appointment->__fillable, $old_data, $appointment->id);
    }

    /**
     * @return void
     */
    public function deleting(Appointments $appointment)
    {
        if (! Auth::check()) {
            return;
        }
        AuditTrails::deleteEventLogger($appointment->__table, 'delete', $appointment->__fillable, $appointment->id);

    }
}
