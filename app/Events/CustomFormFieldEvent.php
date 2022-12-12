<?php

namespace App\Events;

use App\Models\AuditTrails;
use App\Models\CustomFormFields;
use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Support\Facades\Log;

class CustomFormFieldEvent
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
     * @param CustomFormFields $customFormField
     */
    public function created(CustomFormFields $customFormField)
    {
        AuditTrails::addEventLogger($customFormField->__table, 'create', $customFormField->toArray(), $customFormField->__fillable, $customFormField,$customFormField->user_form_id);

    }


    /**
     * @param CustomFormFields $customFormField
     * @return void
     */

    public function updating(CustomFormFields $customFormField)
    {
        $old_data = (CustomFormFields::find($customFormField->id))->toArray();
        AuditTrails::editEventLogger($customFormField->__table, 'Edit', $customFormField->toArray(), $customFormField->__fillable, $old_data, $customFormField->id, $customFormField->user_form_id);
    }


    /**
     * @param CustomFormFields $customFormField
     * @return void
     */

    public function deleting(CustomFormFields $customFormField)

    {
        AuditTrails::deleteEventLogger($customFormField->__table, 'delete', $customFormField->__fillable, $customFormField->id, $customFormField->user_form_id);

    }

}
