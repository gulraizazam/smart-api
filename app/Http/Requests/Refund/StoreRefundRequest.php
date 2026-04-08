<?php

declare(strict_types=1);

namespace App\Http\Requests\Refund;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

final class StoreRefundRequest extends FormRequest
{
    #[\Override]
    public function authorize(): bool
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }

        return $user->can('refunds_create') || $user->can('patients_refund_refund');
    }

    #[\Override]
    public function rules(): array
    {
        return [
            'refund_amount'   => ['required', 'numeric', 'min:1', 'regex:/^[0-9]+$/'],
            'refund_note'     => ['required', 'string', 'max:1000'],
            'package_id'      => ['required', 'integer', 'exists:packages,id'],
            'payment_mode_id' => ['nullable', 'integer', 'exists:payment_modes,id'],
            'created_at'      => ['required', 'date', 'date_format:Y-m-d'],
            'date_backend'    => ['nullable', 'date_format:Y-m-d'],
            'case_setteled'   => ['nullable', 'in:0,1'],
            'patient_id'      => ['nullable', 'integer'],
        ];
    }

    #[\Override]
    public function messages(): array
    {
        return [
            'refund_amount.required'   => 'The refund amount is required.',
            'refund_amount.numeric'    => 'The refund amount must be a number.',
            'refund_amount.min'        => 'The refund amount must be at least 1.',
            'refund_note.required'     => 'The refund note is required.',
            'package_id.required'      => 'The plan is required.',
            'package_id.exists'        => 'The selected plan does not exist.',
            'payment_mode_id.required' => 'The payment mode is required.',
            'created_at.required'      => 'The date field is required.',
            'created_at.date_format'   => 'The date field format is incorrect.',
        ];
    }

    #[\Override]
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'data'    => null,
                'errors'  => $validator->errors()->toArray(),
            ], 422)
        );
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'You are not authorized to access this resource.',
                'data'    => null,
                'errors'  => [],
            ], 403)
        );
    }
}
