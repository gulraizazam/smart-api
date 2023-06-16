<?php

namespace App\Events;

use App\Models\AuditTrails;
use App\Models\CustomForms;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

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

    public function created(CustomForms $customForm)
    {
        AuditTrails::addEventLogger($customForm->__table, 'create', $customForm->toArray(), $customForm->__fillable, $customForm);

    }

    /**
     * @return void
     */
    public function updating(CustomForms $customForm)
    {
        $old_data = (CustomForms::find($customForm->id))->toArray();
        AuditTrails::editEventLogger($customForm->__table, 'Edit', $customForm->toArray(), $customForm->__fillable, $old_data, $customForm->id);
    }

    /**
     * @return void
     */
    public function deleting(CustomForms $customForm)
    {
        AuditTrails::deleteEventLogger($customForm->__table, 'delete', $customForm->__fillable, $customForm->id);

    }
}
