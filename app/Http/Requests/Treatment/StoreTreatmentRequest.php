<?php

declare(strict_types=1);

namespace App\Http\Requests\Treatment;

use App\Helpers\GeneralFunctions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

final class StoreTreatmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        // Normalize phone (handle masked values)
        $phone = $this->input('phone');
        if ($phone === '***********') {
            $phone = $this->input('old_phone');
        }
        if ($phone) {
            $data['phone'] = GeneralFunctions::cleanNumber($phone);
        }

        // Convert scheduled_time from 12-hour to 24-hour format
        if ($this->filled('scheduled_time')) {
            try {
                $time = \Carbon\Carbon::createFromFormat('g:i A', $this->input('scheduled_time'));
                $data['scheduled_time'] = $time->format('H:i:s');
            } catch (\Exception) {
                // Let validation handle invalid format
            }
        }

        // Filter out zero-value IDs
        if ($this->input('city_id') == 0) {
            $this->request->remove('city_id');
        }
        if (empty($this->input('town_id')) || $this->input('town_id') == 0) {
            $this->request->remove('town_id');
        }

        if ($data) {
            $this->merge($data);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'name'              => ['required', 'string', 'max:255'],
            'phone'             => ['required', 'string', 'max:20'],
            'service_id'        => ['required', 'integer', 'exists:services,id'],
            'location_id'       => ['required', 'integer', 'exists:locations,id'],
            'doctor_id'         => ['required', 'integer', 'exists:users,id'],
            'patient_id'        => ['required', 'integer', 'exists:users,id'],
            'scheduled_date'    => ['nullable', 'date'],
            'scheduled_time'    => ['nullable', 'date_format:H:i:s'],
            'base_service_id'   => ['nullable', 'integer', 'exists:services,id'],
            'resource_id'       => ['nullable', 'integer', 'exists:resources,id'],
            'lead_id'           => ['nullable', 'integer', 'exists:leads,id'],
            'start'             => ['nullable', 'date'],
            'end'               => ['nullable', 'date'],
            'old_phone'         => ['nullable', 'string', 'max:20'],
            'gender'            => ['nullable', 'in:0,1,2'],
            'referred_by'       => ['nullable', 'integer', 'exists:users,id'],
            'coming_from'       => ['nullable', 'string', 'max:255'],
            'send_message'      => ['nullable', 'boolean'],
        ];

        if ($this->filled('city_id') && $this->input('city_id') != 0) {
            $rules['city_id'] = ['integer', 'exists:cities,id'];
        }

        if ($this->filled('town_id') && $this->input('town_id') != 0) {
            $rules['town_id'] = ['integer', 'exists:towns,id'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required'        => 'Patient name is required.',
            'phone.required'       => 'Phone number is required.',
            'service_id.required'  => 'Service is required.',
            'service_id.exists'    => 'Invalid service selected.',
            'location_id.required' => 'Location is required.',
            'location_id.exists'   => 'Invalid location selected.',
            'doctor_id.required'   => 'Doctor is required.',
            'doctor_id.exists'     => 'Invalid doctor selected.',
            'patient_id.required'  => 'Patient is required.',
            'patient_id.exists'    => 'Invalid patient selected.',
        ];
    }
}
