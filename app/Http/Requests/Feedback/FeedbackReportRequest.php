<?php

declare(strict_types=1);

namespace App\Http\Requests\Feedback;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class FeedbackReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('feedbacks_manage');
    }

    /**
     * Strip the "all" sentinel emitted by the Centres multi-select before
     * validation runs — otherwise it reaches the integer rule and 422s.
     * Empty input then falls back to the user's permitted centres in the
     * controller.
     */
    protected function prepareForValidation(): void
    {
        $raw = $this->input('centre_id');

        if ($raw === null) {
            return;
        }

        $this->merge([
            'centre_id' => array_values(array_filter(
                (array) $raw,
                fn ($value): bool => $value !== 'all' && $value !== '',
            )),
        ]);
    }

    public function rules(): array
    {
        return [
            'centre_id' => ['nullable', 'array'],
            'centre_id.*' => ['integer', 'exists:locations,id'],
            'doctor_id' => ['nullable', 'integer', 'exists:users,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'date_range' => ['required', 'string', 'regex:/^.+\s-\s.+$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'date_range.required' => 'Date range is required.',
            'date_range.regex' => 'Date range must be in format "start - end".',
        ];
    }
}
