<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Gate;

class UserVoucherStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('vouchers.create');
    }

    public function rules(): array
    {
        return [
            'patient_id' => 'required|integer|exists:users,id',
            'voucher_id' => 'required|integer|exists:discounts,id',
            'amount' => 'required|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'patient_id.required' => 'Patient is required.',
            'patient_id.exists' => 'The specified patient does not exist.',
            'voucher_id.required' => 'Voucher type is required.',
            'voucher_id.exists' => 'The specified voucher does not exist.',
            'amount.required' => 'Amount is required.',
            'amount.min' => 'Amount must be at least 0.',
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
