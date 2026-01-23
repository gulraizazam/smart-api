<?php

namespace App\Http\Requests\Appointment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreAppointmentRequest extends FormRequest
{
    public function authorize()
    {
        return Auth::check();
    }

    protected function prepareForValidation()
    {
        $isConsultation = false;
        
        if ($this->has('appointment_type')) {
            $appointmentTypeStr = strtolower($this->appointment_type);
            $isConsultation = ($appointmentTypeStr === 'consulting' || $appointmentTypeStr === 'consultancy');
            
            if (!$this->has('appointment_type_id')) {
                $appointmentType = \App\Models\AppointmentTypes::where('account_id', Auth::user()->account_id)
                    ->where('slug', 'consultancy')
                    ->first();
                
                if ($appointmentType) {
                    $this->merge(['appointment_type_id' => $appointmentType->id]);
                    
                    if (!$this->has('appointment_status_id')) {
                        // Get default status for this account (not filtered by appointment_type_id)
                        $defaultStatus = \App\Models\AppointmentStatuses::where([
                            'account_id' => Auth::user()->account_id,
                            'is_default' => 1
                        ])->first();
                        
                        if ($defaultStatus) {
                            $this->merge(['appointment_status_id' => $defaultStatus->id]);
                        }
                    }
                }
            }
        }
        
        // Convert scheduled_time from h:mm A format to H:i:s format
        if ($this->has('scheduled_time') && $this->scheduled_time) {
            try {
                $time = \Carbon\Carbon::createFromFormat('g:i A', $this->scheduled_time);
                $this->merge(['scheduled_time' => $time->format('H:i:s')]);
            } catch (\Exception $e) {
                // If conversion fails, leave as is and let validation handle it
            }
        }
        
        if ($isConsultation) {
            $this->request->remove('resource_id');
            $this->request->remove('resource_has_rota_day_id');
            $this->request->remove('resource_has_rota_day_id_for_machine');
        }
        
        if ($this->has('city_id') && ($this->city_id == 0 || $this->city_id === '0')) {
            $this->request->remove('city_id');
        }
        
        if ($this->has('town_id') && ($this->town_id == 0 || $this->town_id === '0' || empty($this->town_id))) {
            $this->request->remove('town_id');
        }
    }

    public function rules()
    {
        $rules = [
            'appointment_type_id' => 'sometimes|exists:appointment_types,id',
            'appointment_status_id' => 'sometimes|exists:appointment_statuses,id',
            'location_id' => 'sometimes|exists:locations,id',
            'service_id' => 'nullable|exists:services,id',
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
            'phone' => 'nullable|string|max:20',
            'old_phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'gender' => 'nullable|in:0,1,2',
            'referred_by' => 'nullable|integer|exists:users,id',
            'start' => 'nullable|date',
            'end' => 'nullable|date',
            'appointment_type' => 'nullable|string',
            'new_patient' => 'nullable|boolean',
        ];

        if ($this->filled('resource_id')) {
            $rules['resource_id'] = 'exists:resources,id';
        }
        
        if ($this->filled('city_id') && $this->city_id != 0) {
            $rules['city_id'] = 'exists:cities,id';
        }
        
        if ($this->filled('town_id') && $this->town_id != 0) {
            $rules['town_id'] = 'exists:towns,id';
        }

        return $rules;
    }

    public function messages()
    {
        return [
            'appointment_type_id.required' => 'Appointment type is required.',
            'appointment_type_id.exists' => 'Invalid appointment type selected.',
            'appointment_status_id.required' => 'Appointment status is required.',
            'appointment_status_id.exists' => 'Invalid appointment status selected.',
            'location_id.required' => 'Location is required.',
            'location_id.exists' => 'Invalid location selected.',
            'service_id.exists' => 'Invalid service selected.',
            'doctor_id.exists' => 'Invalid doctor selected.',
            'scheduled_date.date' => 'Invalid scheduled date format.',
            'scheduled_time.date_format' => 'Invalid scheduled time format.',
        ];
    }
}
