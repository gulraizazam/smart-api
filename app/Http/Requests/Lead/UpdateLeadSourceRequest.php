<?php

declare(strict_types=1);

namespace App\Http\Requests\Lead;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateLeadSourceRequest extends FormRequest
{
    #[\Override]
    public function authorize(): bool
    {
        return Gate::allows('lead_sources_edit');
    }

    #[\Override]
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'active' => ['nullable', 'in:0,1'],
        ];
    }

    #[\Override]
    public function messages(): array
    {
        return [
            'name.required' => 'Lead source name is required.',
        ];
    }
}
