<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreUpdateLeadCommentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('leads.comment.create');
    }

    public function rules(): array
    {
        return [
            'comment' => ['required', 'string', 'max:5000'],
            'lead_id' => ['required', 'integer', 'exists:leads,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'comment.required' => 'Comment text is required.',
            'lead_id.required' => 'Lead ID is required.',
            'lead_id.exists' => 'Lead not found.',
        ];
    }
}
