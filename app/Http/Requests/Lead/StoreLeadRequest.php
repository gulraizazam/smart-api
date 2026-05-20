<?php

declare(strict_types=1);

namespace App\Http\Requests\Lead;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('leads.create');
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
            'child_service_id' => ['nullable', 'integer', 'exists:services,id'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'referred_by' => ['nullable', 'integer', 'exists:users,id'],
            'meta_lead_id' => ['nullable', 'string', 'max:255'],
            'new_lead' => ['nullable', 'in:0,1'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Lead name is required.',
            'phone.required' => 'Phone number is required.',
            'phone.min' => 'Phone number must be at least 10 digits.',
            'gender.required' => 'Gender is required.',
            'gender.in' => 'Gender must be Male (1) or Female (2).',
            'city_id.required' => 'City is required.',
            'city_id.exists' => 'Selected city does not exist.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->phone === '***********' && $this->old_phone) {
            $this->merge(['phone' => $this->old_phone]);
        }
    }
}
