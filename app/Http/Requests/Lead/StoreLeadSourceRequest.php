<?php

namespace App\Http\Requests\Lead;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreLeadSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('lead_sources_create');
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'active' => 'nullable|in:0,1',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Lead source name is required.',
        ];
    }
}
