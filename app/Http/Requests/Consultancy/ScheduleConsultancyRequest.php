<?php

declare(strict_types=1);

namespace App\Http\Requests\Consultancy;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class ScheduleConsultancyRequest extends FormRequest
{
    #[\Override]
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * @return array<string, string>
     */
    #[\Override]
    public function rules(): array
    {
        return [
            'start' => 'required|date',
            'doctor_id' => 'required|exists:users,id',
            'location_id' => 'required|exists:locations,id',
        ];
    }

    /**
     * @return array<string, string>
     */
    #[\Override]
    public function messages(): array
    {
        return [
            'start.required' => 'The schedule date/time is required.',
            'start.date' => 'Invalid schedule date/time format.',
            'doctor_id.required' => 'Doctor is required for scheduling.',
            'doctor_id.exists' => 'Invalid doctor selected.',
            'location_id.required' => 'Location is required for scheduling.',
            'location_id.exists' => 'Invalid location selected.',
        ];
    }

    public function toServiceData(): array
    {
        return [
            'start' => $this->start,
            'doctor_id' => $this->doctor_id,
            'location_id' => $this->location_id,
            'reschedule' => true,
        ];
    }
}
