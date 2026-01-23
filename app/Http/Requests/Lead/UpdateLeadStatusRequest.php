<?php

namespace App\Http\Requests\Lead;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateLeadStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('leads_lead_status');
    }

    public function rules(): array
    {
        return [
            'id' => 'required|numeric|exists:leads,id',
            'lead_status_parent_id' => 'required|numeric|exists:lead_statuses,id',
            'lead_status_chalid_id' => 'nullable|numeric|exists:lead_statuses,id',
            'comment1' => 'nullable|string|max:1000',
            'comment2' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'id.required' => 'Lead ID is required.',
            'id.exists' => 'Lead not found.',
            'lead_status_parent_id.required' => 'Lead status is required.',
        ];
    }
}
