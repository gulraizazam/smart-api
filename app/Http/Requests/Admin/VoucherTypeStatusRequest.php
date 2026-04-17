<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Gate;

class VoucherTypeStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::any(['voucher_types_active', 'voucher_types_inactive']);
    }

    public function rules(): array
    {
        return [
            'id' => 'required|integer|exists:discounts,id',
            'status' => 'required|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'id.required' => 'Voucher type ID is required.',
            'id.exists' => 'The specified voucher type does not exist.',
            'status.required' => 'Status is required.',
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
