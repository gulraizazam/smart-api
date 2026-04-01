<?php

declare(strict_types=1);

namespace App\Http\Requests\Feedback;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class GetTreatmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('feedbacks_create');
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'patient_id.required' => 'Patient selection is required.',
            'patient_id.exists' => 'Selected patient does not exist.',
        ];
    }
}
