<?php

declare(strict_types=1);

namespace App\Http\Requests\Treatment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

final class UpdateTreatmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    protected function prepareForValidation(): void
    {
        // Convert scheduled_time from 12-hour to 24-hour format
        if ($this->filled('scheduled_time')) {
            try {
                $time = \Carbon\Carbon::parse($this->input('scheduled_time'));
                $this->merge(['scheduled_time' => $time->format('H:i:s')]);
            } catch (\Exception) {
                // Let validation handle invalid format
            }
        }

        // Remove resource fields for consultancy type
        $appointmentType = strtolower((string) $this->input('appointment_type', ''));
        if (in_array($appointmentType, ['consulting', 'consultancy'], true)) {
            $this->request->remove('resource_id');
            $this->request->remove('resource_has_rota_day_id');
            $this->request->remove('resource_has_rota_day_id_for_machine');
        } elseif ($this->filled('resource_id')) {
            $exists = \App\Models\Resources::where('id', $this->input('resource_id'))->exists();
            if (!$exists) {
                $this->request->remove('resource_id');
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'appointment_type_id'        => ['sometimes', 'exists:appointment_types,id'],
            'appointment_status_id'      => ['sometimes', 'exists:appointment_statuses,id'],
            'base_appointment_status_id' => ['sometimes', 'exists:appointment_statuses,id'],
            'location_id'                => ['sometimes', 'exists:locations,id'],
            'service_id'                 => ['nullable', 'exists:services,id'],
            'treatment_service_id'       => ['nullable', 'exists:services,id'],
            'doctor_id'                  => ['nullable', 'exists:users,id'],
            'patient_id'                 => ['nullable', 'exists:users,id'],
            'lead_id'                    => ['nullable', 'exists:leads,id'],
            'scheduled_date'             => ['nullable', 'date'],
            'scheduled_time'             => ['nullable', 'date_format:H:i:s'],
            'name'                       => ['nullable', 'string', 'max:255'],
            'phone'                      => ['nullable', 'string', 'max:20'],
            'gender'                     => ['nullable', 'in:0,1,2'],
            'consultancy_type'           => ['nullable', 'in:in_person,online'],
            'coming_from'                => ['nullable', 'string', 'max:255'],
            'reason'                     => ['nullable', 'string'],
            'send_message'               => ['nullable', 'boolean'],
            'reschedule'                 => ['nullable', 'boolean'],
        ];

        if ($this->filled('resource_id')) {
            $rules['resource_id'] = ['exists:resources,id'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'location_id.exists'   => 'Invalid location selected.',
            'service_id.exists'    => 'Invalid service selected.',
            'doctor_id.exists'     => 'Invalid doctor selected.',
            'scheduled_date.date'  => 'Invalid scheduled date format.',
        ];
    }
}
