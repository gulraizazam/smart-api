<?php

declare(strict_types=1);

namespace App\Http\Requests\Lead;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreLeadDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('leads.departments.manage');
    }

    public function rules(): array
    {
        $accountId = (int) (Auth::user()?->account_id ?? 0);

        return [
            'name' => [
                'required', 'string', 'max:120',
                // Per-tenant uniqueness — matches the DB unique index.
                // whereNull('deleted_at') so a soft-deleted "Skin" doesn't
                // block a new "Skin" here (matches how lead_sources handles
                // soft-delete recovery).
                Rule::unique('lead_departments', 'name')
                    ->where('account_id', $accountId)
                    ->whereNull('deleted_at'),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Department name is required.',
            'name.unique' => 'A department with this name already exists.',
        ];
    }
}
