<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class AssignVoucherRequest extends FormRequest
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
