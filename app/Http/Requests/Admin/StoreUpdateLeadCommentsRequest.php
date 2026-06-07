<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

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
            // FK validation scoped to the caller's account + non-deleted rows
            // (security audit 2026-06): a bare exists: let the client point at
            // another tenant's lead (cross-tenant FK injection).
            'lead_id' => [
                'required',
                'integer',
                Rule::exists('leads', 'id')
                    ->where('account_id', Auth::user()?->account_id)
                    ->whereNull('deleted_at'),
            ],
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
