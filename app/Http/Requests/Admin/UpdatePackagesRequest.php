<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePackagesRequest extends FormRequest
{
    #[\Override]
    public function authorize(): bool
    {
        return true;
    }

    #[\Override]
    public function rules(): array
    {
        return [
            'random_id'      => ['required'],
            'appointment_id' => ['nullable', 'exists:appointments,id'],
        ];
    }

    #[\Override]
    public function messages(): array
    {
        return [
            'random_id.required'    => 'Package identifier is required',
            'appointment_id.exists' => 'Appointment not found',
        ];
    }
}
