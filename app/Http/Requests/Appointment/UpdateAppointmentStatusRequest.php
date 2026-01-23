<?php

namespace App\Http\Requests\Appointment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateAppointmentStatusRequest extends FormRequest
{
    public function authorize()
    {
        return Auth::check();
    }

    public function rules()
    {
        return [
            'appointment_status_id' => 'required|exists:appointment_statuses,id',
            'reason' => 'nullable|string',
            'cancellation_reason_id' => 'nullable|exists:cancellation_reasons,id',
            'send_message' => 'nullable|boolean',
        ];
    }

    public function messages()
    {
        return [
            'appointment_status_id.required' => 'Appointment status is required.',
            'appointment_status_id.exists' => 'Invalid appointment status selected.',
            'cancellation_reason_id.exists' => 'Invalid cancellation reason selected.',
        ];
    }
}
