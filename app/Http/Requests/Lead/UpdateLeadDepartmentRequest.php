<?php

declare(strict_types=1);

namespace App\Http\Requests\Lead;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateLeadDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('leads.departments.manage');
    }

    public function rules(): array
    {
        $accountId = (int) (Auth::user()?->account_id ?? 0);
        $id = (int) $this->route('id');

        return [
            'name' => [
                'sometimes', 'required', 'string', 'max:120',
                Rule::unique('lead_departments', 'name')
                    ->where('account_id', $accountId)
                    ->whereNull('deleted_at')
                    ->ignore($id, 'id'),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'A department with this name already exists.',
        ];
    }
}
