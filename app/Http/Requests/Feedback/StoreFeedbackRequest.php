<?php

declare(strict_types=1);

namespace App\Http\Requests\Feedback;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('feedbacks_create');
    }

    public function rules(): array
    {
        return [
            // FK validation scoped to the caller's account + non-deleted rows
            // (security audit 2026-06): a bare exists: let the client point at
            // another tenant's appointment (cross-tenant FK injection).
            'treatment' => [
                'required',
                'integer',
                Rule::exists('appointments', 'id')
                    ->where('account_id', Auth::user()?->account_id)
                    ->whereNull('deleted_at'),
            ],
            'rating' => ['required', 'integer', 'min:1', 'max:10'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'treatment.required' => 'Treatment selection is required.',
            'treatment.exists' => 'Selected treatment does not exist.',
            'rating.required' => 'Rating is required.',
            'rating.min' => 'Rating must be at least 1.',
            'rating.max' => 'Rating must not exceed 10.',
        ];
    }
}
