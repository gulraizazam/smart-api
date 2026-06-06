<?php

declare(strict_types=1);

namespace App\Http\Requests\Feedback;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('feedbacks_edit');
    }

    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'min:1', 'max:10'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'rating.required' => 'Rating is required.',
            'rating.min' => 'Rating must be at least 1.',
            'rating.max' => 'Rating must not exceed 10.',
        ];
    }
}
