<?php

declare(strict_types=1);

namespace App\Http\Requests\Feedback;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class GetTreatmentInfoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('feedbacks_create');
    }

    public function rules(): array
    {
        return [
            'treatment_id' => ['required', 'integer', 'exists:appointments,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'treatment_id.required' => 'Treatment selection is required.',
            'treatment_id.exists' => 'Selected treatment does not exist.',
        ];
    }
}
