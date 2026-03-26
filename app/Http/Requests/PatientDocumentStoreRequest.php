<?php

namespace App\Http\Requests;

use App\HelperModule\ApiHelper;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class PatientDocumentStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'file' => 'required|file|mimes:jpg,jpeg,png,pdf,docx,xlsx|max:10240',
            'patient_id' => 'required|integer|exists:users,id',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'The document name is required.',
            'file.required' => 'No file was uploaded. Please select a file.',
            'file.mimes' => 'File format not supported. Allowed: jpg, jpeg, png, pdf, docx, xlsx',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiHelper::apiResponse(
                config('constants.api_status.success'),
                $validator->errors()->first(),
                false
            )
        );
    }
}
