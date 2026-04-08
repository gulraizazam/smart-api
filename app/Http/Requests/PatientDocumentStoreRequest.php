<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class PatientDocumentStoreRequest extends FormRequest
{
    #[\Override]
    public function authorize(): bool
    {
        return true;
    }

    #[\Override]
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'file' => 'required|file|mimes:jpg,jpeg,png,pdf,docx,xlsx|max:10240',
            'patient_id' => 'required|integer|exists:users,id',
        ];
    }

    #[\Override]
    public function messages(): array
    {
        return [
            'name.required' => 'The document name is required.',
            'file.required' => 'No file was uploaded. Please select a file.',
            'file.mimes' => 'File format not supported. Allowed: jpg, jpeg, png, pdf, docx, xlsx',
        ];
    }

    #[\Override]
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'data' => null,
                'errors' => $validator->errors()->all(),
            ], 422)
        );
    }
}
