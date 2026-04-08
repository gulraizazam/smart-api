<?php

declare(strict_types=1);

namespace App\Http\Requests\Discount;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

final class ToggleStatusRequest extends FormRequest
{
    #[\Override]
    public function authorize(): bool
    {
        return $this->user()?->can('discounts_active') ?? false;
    }

    #[\Override]
    public function rules(): array
    {
        return [
            'id'     => ['required', 'integer', 'exists:discounts,id'],
            'status' => ['required', 'in:0,1'],
        ];
    }

    #[\Override]
    public function messages(): array
    {
        return [
            'id.required'     => 'The discount ID is required.',
            'id.exists'       => 'The selected discount does not exist.',
            'status.required' => 'The status is required.',
            'status.in'       => 'The status must be 0 or 1.',
        ];
    }

    #[\Override]
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'status'  => false,
                'message' => $validator->errors()->first(),
                'data'    => null,
            ], 200)
        );
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            response()->json([
                'status'  => false,
                'message' => 'You are not authorized to access this resource.',
                'data'    => null,
            ], 401)
        );
    }
}
