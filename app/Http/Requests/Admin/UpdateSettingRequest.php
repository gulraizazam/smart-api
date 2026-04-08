<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'data' => 'nullable|string|max:1000',
            'min' => 'nullable|numeric|min:0',
            'max' => 'nullable|numeric|min:0',
            'pre' => 'nullable|integer|min:0',
            'post' => 'nullable|integer|min:0',
            'is_featured' => 'nullable|in:0,1',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Setting name is required.',
            'min.numeric' => 'Min value must be a number.',
            'max.numeric' => 'Max value must be a number.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'status' => false,
            'message' => $validator->errors()->first(),
            'data' => $validator->errors(),
        ], (int) config('constants.api_status.success', 200)));
    }
}
