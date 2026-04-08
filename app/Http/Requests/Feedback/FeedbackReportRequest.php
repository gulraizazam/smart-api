<?php

declare(strict_types=1);

namespace App\Http\Requests\Feedback;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class FeedbackReportRequest extends FormRequest
{
    #[\Override]
    public function authorize(): bool
    {
        return Gate::allows('feedbacks_manage');
    }

    #[\Override]
    public function rules(): array
    {
        return [
            'centre_id' => ['nullable', 'integer', 'exists:locations,id'],
            'doctor_id' => ['nullable', 'integer', 'exists:users,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'date_range' => ['required', 'string', 'regex:/^.+\s-\s.+$/'],
        ];
    }

    #[\Override]
    public function messages(): array
    {
        return [
            'date_range.required' => 'Date range is required.',
            'date_range.regex' => 'Date range must be in format "start - end".',
        ];
    }
}
