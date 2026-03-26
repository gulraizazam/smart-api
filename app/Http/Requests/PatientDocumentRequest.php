<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PatientDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'document_type' => 'required|string|in:consent_form,consultation_form,others',
            'file' => $this->isMethod('POST')
                ? 'required|file|mimes:jpg,jpeg,png,pdf,docx,xlsx|max:10240'
                : 'nullable|file|mimes:jpg,jpeg,png,pdf,docx,xlsx|max:10240',
        ];
    }
}
