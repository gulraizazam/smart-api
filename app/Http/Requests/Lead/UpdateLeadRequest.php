<?php

declare(strict_types=1);

namespace App\Http\Requests\Lead;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('leads.edit');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'min:10', 'max:15'],
            'gender' => ['required', 'numeric', 'in:1,2'],
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'email' => ['nullable', 'email', 'max:255'],
            'lead_source_id' => ['nullable', 'integer', 'exists:lead_sources,id'],
            'lead_status_id' => ['nullable', 'integer', 'exists:lead_statuses,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'child_service_id' => ['nullable', 'array'],
            'child_service_id.*' => ['nullable', 'integer', 'exists:services,id'],
            'old_service' => ['nullable', 'integer'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'referred_by' => ['nullable', 'integer', 'exists:users,id'],
            // Tenant-scoped exists — see StoreLeadRequest for the rationale.
            'department_id' => [
                'nullable', 'integer',
                Rule::exists('lead_departments', 'id')
                    ->where('account_id', (int) (Auth::user()?->account_id ?? 0))
                    ->whereNull('deleted_at'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Lead name is required.',
            'phone.required' => 'Phone number is required.',
            'gender.required' => 'Gender is required.',
            'city_id.required' => 'City is required.',
        ];
    }
}
