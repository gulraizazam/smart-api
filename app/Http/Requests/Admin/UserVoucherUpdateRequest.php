<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Gate;

class UserVoucherUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('vouchers.edit');
    }

    public function rules(): array
    {
        return [
            'total_amount' => 'required|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'total_amount.required' => 'Total amount is required.',
            'total_amount.min' => 'Total amount must be at least 0.',
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
