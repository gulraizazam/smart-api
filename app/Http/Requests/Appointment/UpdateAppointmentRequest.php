<?php

namespace App\Http\Requests\Appointment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateAppointmentRequest extends FormRequest
{
    public function authorize()
    {
        return Auth::check();
    }

    protected function prepareForValidation()
    {
        $data = $this->all();
        
        // Convert scheduled_time from 12-hour format to 24-hour format if needed
        if (isset($data['scheduled_time']) && $data['scheduled_time']) {
            try {
                $time = \Carbon\Carbon::parse($data['scheduled_time']);
                $data['scheduled_time'] = $time->format('H:i:s');
            } catch (\Exception $e) {
                // If parsing fails, leave as is and let validation handle it
            }
        }
        
        if (isset($data['appointment_type']) && 
            (strtolower($data['appointment_type']) === 'consulting' || 
             strtolower($data['appointment_type']) === 'consultancy')) {
            unset($data['resource_id']);
            unset($data['resource_has_rota_day_id']);
            unset($data['resource_has_rota_day_id_for_machine']);
        } elseif (isset($data['resource_id'])) {
            $resourceExists = \App\Models\Resources::find($data['resource_id']);
            if (!$resourceExists) {
                unset($data['resource_id']);
            }
        }
        
        $this->replace($data);
    }

    public function rules()
    {
        $rules = [
            'appointment_type_id' => 'sometimes|exists:appointment_types,id',
            'appointment_status_id' => 'sometimes|exists:appointment_statuses,id',
            'location_id' => 'sometimes|exists:locations,id',
            'service_id' => 'nullable|exists:services,id',
            'treatment_service_id' => 'nullable|exists:services,id',
            'doctor_id' => 'nullable|exists:users,id',
            'patient_id' => 'nullable|exists:users,id',
            'lead_id' => 'nullable|exists:leads,id',
            'scheduled_date' => 'nullable|date',
            'scheduled_time' => 'nullable|date_format:H:i:s',
            'name' => 'nullable|string|max:255',
            'consultancy_type' => 'nullable|in:in_person,online',
            'coming_from' => 'nullable|string|max:255',
            'reason' => 'nullable|string',
            'send_message' => 'nullable|boolean',
            'reschedule' => 'nullable|boolean',
        ];

        if ($this->filled('resource_id')) {
            $rules['resource_id'] = 'exists:resources,id';
        }

        return $rules;
    }

    public function messages()
    {
        return [
            'appointment_type_id.exists' => 'Invalid appointment type selected.',
            'appointment_status_id.exists' => 'Invalid appointment status selected.',
            'location_id.exists' => 'Invalid location selected.',
            'service_id.exists' => 'Invalid service selected.',
            'doctor_id.exists' => 'Invalid doctor selected.',
            'scheduled_date.date' => 'Invalid scheduled date format.',
            'scheduled_time.date_format' => 'Invalid scheduled time format.',
        ];
    }
}
