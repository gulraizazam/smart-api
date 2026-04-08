<?php

declare(strict_types=1);

namespace App\Http\Requests\Treatment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

final class CheckPatientLastTreatmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'patient_id'             => ['required', 'integer', 'exists:users,id'],
            'service_id'             => ['required', 'integer', 'exists:services,id'],
            'location_id'            => ['required', 'integer', 'exists:locations,id'],
            'exclude_appointment_id' => ['nullable', 'integer'],
            'start'                  => ['nullable', 'date'],
        ];
    }
}
