<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class PatientImageRequest extends FormRequest
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
            'patient_id' => 'required|integer|exists:users,id',
            'file' => 'required|file|mimes:jpg,jpeg,png,gif|max:5120',
        ];
    }

    #[\Override]
    public function messages(): array
    {
        return [
            'file.required' => 'Please provide a valid image.',
            'file.mimes' => 'JPG, JPEG, PNG, GIF only allowed.',
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
                'errors' => $validator->errors()->toArray(),
            ], 422)
        );
    }
}
