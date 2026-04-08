<?php

declare(strict_types=1);

namespace App\Http\Requests\Treatment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

final class RescheduleTreatmentRequest extends FormRequest
{
    #[\Override]
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function rules(): array
    {
        return [
            'id'         => ['required', 'integer', 'exists:appointments,id'],
            'start'      => ['required', 'date'],
            'end'        => ['required', 'date'],
            'doctor_id'  => ['required', 'integer', 'exists:users,id'],
            'resourceId' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    #[\Override]
    public function messages(): array
    {
        return [
            'id.required'        => 'Appointment ID is required.',
            'id.exists'          => 'Appointment not found.',
            'start.required'     => 'Start time is required.',
            'end.required'       => 'End time is required.',
            'doctor_id.required' => 'Doctor is required.',
            'doctor_id.exists'   => 'Invalid doctor selected.',
        ];
    }
}
