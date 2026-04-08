<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Brand;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreBrandRequest extends FormRequest
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
        ];
    }

    #[\Override]
    public function messages(): array
    {
        return [
            'name.required' => 'Brand name is required.',
        ];
    }

    #[\Override]
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'status' => false,
            'message' => $validator->errors()->first(),
            'data' => $validator->errors(),
        ], (int) config('constants.api_status.success', 200)));
    }
}
