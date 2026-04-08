<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UserVoucherUpdateRequest extends FormRequest
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
            'total_amount' => 'required|numeric|min:0',
        ];
    }

    #[\Override]
    public function messages(): array
    {
        return [
            'total_amount.required' => 'Total amount is required.',
            'total_amount.min' => 'Total amount must be at least 0.',
        ];
    }

    #[\Override]
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
