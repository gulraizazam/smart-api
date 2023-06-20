<?php

namespace App\Events;

use App\Models\AuditTrails;
use App\Models\CustomFormFields;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

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

    public function created(CustomFormFields $customFormField)
    {
        AuditTrails::addEventLogger($customFormField->__table, 'create', $customFormField->toArray(), $customFormField->__fillable, $customFormField, $customFormField->user_form_id);

    }

    /**
     * @return void
     */
    public function updating(CustomFormFields $customFormField)
    {
        $old_data = (CustomFormFields::find($customFormField->id))->toArray();
        AuditTrails::editEventLogger($customFormField->__table, 'Edit', $customFormField->toArray(), $customFormField->__fillable, $old_data, $customFormField->id, $customFormField->user_form_id);
    }

    /**
     * @return void
     */
    public function deleting(CustomFormFields $customFormField)
    {
        AuditTrails::deleteEventLogger($customFormField->__table, 'delete', $customFormField->__fillable, $customFormField->id, $customFormField->user_form_id);

    }
}
