<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UserOperatorSettingsUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'test_mode' => 'required|in:0,1',
            'operator_name' => 'nullable|string|max:255',
            'url' => 'nullable|string|max:500',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:255',
            'mask' => 'nullable|string|max:255',
            'string_1' => 'nullable|string|max:500',
            'string_2' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'test_mode.required' => 'Test mode field is required.',
            'test_mode.in' => 'Test mode must be either 0 or 1.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => $validator->errors()->first(),
            'data' => null,
            'errors' => $validator->errors()->all(),
        ], 422));
    }
}
