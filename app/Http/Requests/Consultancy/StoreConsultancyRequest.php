<?php

declare(strict_types=1);

namespace App\Http\Requests\Consultancy;

use App\Models\AppointmentStatuses;
use App\Models\AppointmentTypes;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreConsultancyRequest extends FormRequest
{
    #[\Override]
    public function authorize(): bool
    {
        return Auth::check();
    }

    #[\Override]
    protected function prepareForValidation(): void
    {
        // Auto-resolve appointment_type_id for consultancy
        if (!$this->has('appointment_type_id')) {
            $appointmentType = AppointmentTypes::where('account_id', Auth::user()->account_id)
                ->where('slug', 'consultancy')
                ->first();

            if ($appointmentType) {
                $this->merge(['appointment_type_id' => $appointmentType->id]);
            }
        }

        // Set default appointment status if not provided
        if (!$this->has('appointment_status_id')) {
            $defaultStatus = AppointmentStatuses::where([
                'account_id' => Auth::user()->account_id,
                'is_default' => 1,
            ])->first();

            if ($defaultStatus) {
                $this->merge([
                    'appointment_status_id' => $defaultStatus->id,
                    'base_appointment_status_id' => $defaultStatus->id,
                ]);
            }
        }

        // Convert scheduled_time from 12-hour to 24-hour format
        if ($this->filled('scheduled_time')) {
            try {
                $time = Carbon::createFromFormat('g:i A', $this->scheduled_time);
                $this->merge(['scheduled_time' => $time->format('H:i:s')]);
            } catch (\Exception) {
                // Let validation handle invalid format
            }
        }

        // Remove resource fields (not applicable for consultancy)
        $this->request->remove('resource_id');
        $this->request->remove('resource_has_rota_day_id');
        $this->request->remove('resource_has_rota_day_id_for_machine');

        // Clean zero-value city_id and town_id
        if ($this->has('city_id') && in_array($this->city_id, [0, '0'], true)) {
            $this->request->remove('city_id');
        }

        if ($this->has('town_id') && (in_array($this->town_id, [0, '0'], true) || empty($this->town_id))) {
            $this->request->remove('town_id');
        }
    }

    /**
     * @return array<string, string>
     */
    #[\Override]
    public function rules(): array
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
            'consultancy_type' => 'nullable|in:in_person,virtual',
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
            'new_patient' => 'nullable|boolean',
        ];

        if ($this->filled('city_id') && $this->city_id != 0) {
            $rules['city_id'] = 'exists:cities,id';
        }

        if ($this->filled('town_id') && $this->town_id != 0) {
            $rules['town_id'] = 'exists:towns,id';
        }

        return $rules;
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
            'scheduled_time.date_format' => 'Invalid scheduled time format. Expected H:i:s.',
            'consultancy_type.in' => 'Consultancy type must be in_person or virtual.',
        ];
    }
}
