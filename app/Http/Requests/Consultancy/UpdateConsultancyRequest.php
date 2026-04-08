<?php

declare(strict_types=1);

namespace App\Http\Requests\Consultancy;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateConsultancyRequest extends FormRequest
{
    #[\Override]
    public function authorize(): bool
    {
        return Auth::check();
    }

    #[\Override]
    protected function prepareForValidation(): void
    {
        // Convert scheduled_time to 24-hour format if needed
        if ($this->filled('scheduled_time')) {
            try {
                $time = Carbon::parse($this->scheduled_time);
                $this->merge(['scheduled_time' => $time->format('H:i:s')]);
            } catch (\Exception) {
                // Let validation handle invalid format
            }
        }

        // Remove resource fields (not applicable for consultancy)
        $data = $this->all();
        unset($data['resource_id'], $data['resource_has_rota_day_id'], $data['resource_has_rota_day_id_for_machine']);
        $this->replace($data);
    }

    /**
     * @return array<string, string>
     */
    #[\Override]
    public function rules(): array
    {
        return [
            'appointment_type_id' => 'sometimes|exists:appointment_types,id',
            'appointment_status_id' => 'sometimes|exists:appointment_statuses,id',
            'location_id' => 'sometimes|exists:locations,id',
            'service_id' => 'nullable|exists:services,id',
            'treatment_id' => 'nullable|exists:services,id',
            'treatment_service_id' => 'nullable|exists:services,id',
            'doctor_id' => 'nullable|exists:users,id',
            'patient_id' => 'nullable|exists:users,id',
            'lead_id' => 'nullable|exists:leads,id',
            'scheduled_date' => 'nullable|date',
            'scheduled_time' => 'nullable|date_format:H:i:s',
            'name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'gender' => 'nullable|in:0,1,2',
            'consultancy_type' => 'nullable|in:in_person,virtual',
            'coming_from' => 'nullable|string|max:255',
            'reason' => 'nullable|string',
            'send_message' => 'nullable|boolean',
            'reschedule' => 'nullable|boolean',
        ];
    }

    /**
     * @return array<string, string>
     */
    #[\Override]
    public function messages(): array
    {
        return [
            'appointment_type_id.exists' => 'Invalid appointment type selected.',
            'appointment_status_id.exists' => 'Invalid appointment status selected.',
            'location_id.exists' => 'Invalid location selected.',
            'service_id.exists' => 'Invalid service selected.',
            'doctor_id.exists' => 'Invalid doctor selected.',
            'scheduled_date.date' => 'Invalid scheduled date format.',
            'scheduled_time.date_format' => 'Invalid scheduled time format.',
            'consultancy_type.in' => 'Consultancy type must be in_person or virtual.',
        ];
    }
}
