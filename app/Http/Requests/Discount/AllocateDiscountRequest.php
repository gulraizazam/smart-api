<?php

declare(strict_types=1);

namespace App\Http\Requests\Discount;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

final class AllocateDiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('discounts.allocate') ?? false;
    }

    public function rules(): array
    {
        return [
            'discount_id'      => ['required', 'integer', 'exists:discounts,id'],
            'location_id'      => ['required', 'integer', 'exists:locations,id'],
            'allocation_type'  => ['required', 'string', 'in:Fixed,Percentage'],
            'allocation_amount' => ['required', 'numeric', 'min:0'],
            'service_ids'      => ['required', 'array', 'min:1'],
            'service_ids.*'    => ['integer', 'exists:services,id'],
            'allocation_slug'  => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'discount_id.required'      => 'The discount is required.',
            'location_id.required'      => 'The location is required.',
            'allocation_type.required'  => 'The allocation type is required.',
            'allocation_type.in'        => 'The allocation type must be Fixed or Percentage.',
            'allocation_amount.required' => 'The allocation amount is required.',
            'service_ids.required'      => 'At least one service must be selected.',
            'service_ids.min'           => 'At least one service must be selected.',
        ];
    }

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
            ], 403)
        );
    }
}
