<?php

declare(strict_types=1);

namespace App\Http\Requests\Feedback;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class GetTreatmentInfoRequest extends FormRequest
{
    #[\Override]
    public function authorize(): bool
    {
        return Gate::allows('feedbacks_create');
    }

    #[\Override]
    public function rules(): array
    {
        return [
            'treatment_id' => ['nullable', 'integer', 'exists:appointments,id'],
        ];
    }
}
