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
            'patient_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
