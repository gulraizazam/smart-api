<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

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

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'data' => null,
                'errors' => $validator->errors()->toArray(),
            ], 422)
        );
    }
}
