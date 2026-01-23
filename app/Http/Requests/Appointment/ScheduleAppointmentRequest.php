<?php

namespace App\Http\Requests\Appointment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class ScheduleAppointmentRequest extends FormRequest
{
    public function authorize()
    {
        return Auth::check();
    }

    public function rules()
    {
        return [
            'appointment_id' => 'required|exists:appointments,id',
            'start' => 'required|date',
            'doctor_id' => 'nullable|exists:users,id',
            'resource_id' => 'nullable|exists:resources,id',
            'location_id' => 'required|exists:locations,id',
            'reschedule' => 'nullable|boolean',
        ];
    }

    public function messages()
    {
        return [
            'appointment_id.required' => 'Appointment ID is required.',
            'appointment_id.exists' => 'Invalid appointment selected.',
            'start.required' => 'Schedule date and time is required.',
            'start.date' => 'Invalid schedule date format.',
            'location_id.required' => 'Location is required.',
            'location_id.exists' => 'Invalid location selected.',
            'doctor_id.exists' => 'Invalid doctor selected.',
            'resource_id.exists' => 'Invalid resource selected.',
        ];
    }
}
