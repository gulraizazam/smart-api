<?php

declare(strict_types=1);
namespace App\Http\Requests\Appointment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateAppointmentStatusRequest extends FormRequest
{
    #[\Override]
    public function authorize(): bool
    {
        return Auth::check();
    }

    #[\Override]
    public function rules(): array
    {
        return [
            'appointment_status_id' => 'required|exists:appointment_statuses,id',
            'reason' => 'nullable|string',
            'cancellation_reason_id' => 'nullable|exists:cancellation_reasons,id',
            'send_message' => 'nullable|boolean',
        ];
    }

    #[\Override]
    public function messages()
    {
        return [
            'appointment_status_id.required' => 'Appointment status is required.',
            'appointment_status_id.exists' => 'Invalid appointment status selected.',
            'cancellation_reason_id.exists' => 'Invalid cancellation reason selected.',
        ];
    }
}
