<?php

declare(strict_types=1);

namespace App\Http\Requests\Lead;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ImportLeadsRequest extends FormRequest
{
    #[\Override]
    public function authorize(): bool
    {
        return Gate::allows('leads_import');
    }

    #[\Override]
    public function rules(): array
    {
        return [
            'leads_file' => ['required', 'file', 'max:2048', 'mimes:xls,xlsx,csv,txt'],
            'update_records' => ['nullable', 'in:0,1'],
            'skip_lead_statuses' => ['nullable', 'in:0,1'],
        ];
    }

    #[\Override]
    public function messages(): array
    {
        return [
            'leads_file.required' => 'Please select a file to import.',
            'leads_file.max' => 'File size must not exceed 2MB.',
            'leads_file.mimes' => 'File must be an Excel (.xls, .xlsx) or CSV file.',
        ];
    }
}
