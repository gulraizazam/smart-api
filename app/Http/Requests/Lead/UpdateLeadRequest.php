<?php

namespace App\Http\Requests\Lead;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('leads_edit');
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'phone' => 'required|string|min:10|max:15',
            'gender' => 'required|numeric|in:1,2',
            'city_id' => 'required|numeric|exists:cities,id',
            'email' => 'nullable|email|max:255',
            'lead_source_id' => 'nullable|numeric|exists:lead_sources,id',
            'lead_status_id' => 'nullable|numeric|exists:lead_statuses,id',
            'service_id' => 'nullable|numeric|exists:services,id',
            'child_service_id' => 'nullable|array',
            'child_service_id.*' => 'nullable|numeric|exists:services,id',
            'old_service' => 'nullable|numeric',
            'location_id' => 'nullable|numeric|exists:locations,id',
            'referred_by' => 'nullable|numeric|exists:users,id',
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
