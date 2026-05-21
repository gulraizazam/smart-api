<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Gate;

class AssignVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Patient-card "assign voucher" action — either a Voucher-Types
        // user (catalogue side) or a Vouchers user (issued side) can
        // legitimately create a user-voucher row.
        return Gate::any(['voucher_types.assign', 'vouchers.create']);
    }

    public function rules(): array
    {
        return [
            'patient_id' => 'sometimes|integer|exists:users,id',
            'id' => 'sometimes|integer|exists:users,id',
            'voucher_id' => 'required|integer|exists:discounts,id',
            'amount' => 'required|numeric|min:0',
        ];
    }

    public function patientId(): int
    {
        return (int) ($this->input('patient_id') ?? $this->input('id'));
    }

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
