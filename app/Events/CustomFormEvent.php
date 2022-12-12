<?php

namespace App\Events;

use App\Models\AuditTrails;
use App\Models\CustomForms;
use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Support\Facades\Log;

class CustomFormEvent
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
     * @param CustomForms $customForm
     */
    public function created(CustomForms $customForm)
    {
        AuditTrails::addEventLogger($customForm->__table, 'create', $customForm->toArray(), $customForm->__fillable, $customForm);

    }


    /**
     * @param CustomForms $customForm
     * @return void
     */

    public function updating(CustomForms $customForm)
    {
        $old_data = (CustomForms::find($customForm->id))->toArray();
        AuditTrails::editEventLogger($customForm->__table, 'Edit', $customForm->toArray(), $customForm->__fillable, $old_data, $customForm->id);
    }


    /**
     * @param CustomForms $customForm
     * @return void
     */

    public function deleting(CustomForms $customForm)

    {
        AuditTrails::deleteEventLogger($customForm->__table, 'delete', $customForm->__fillable, $customForm->id);

    }

}
