<?php

declare(strict_types=1);

namespace App\Http\Requests\Discount;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

final class StoreDiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('discounts.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'name'          => ['required', 'string', 'max:255'],
            'type'          => ['required', 'string', 'in:Fixed,Percentage,Configurable'],
            'amount'        => ['nullable', 'numeric', 'min:0'],
            'discount_type' => ['required', 'string', 'in:Treatment,Consultancy'],
            'slug'          => ['nullable', 'string', 'max:100'],
            'start'         => ['required', 'date'],
            'end'           => ['required', 'date', 'after_or_equal:start'],
            'active'        => ['nullable', 'in:0,1'],
            'pre_days'      => ['nullable', 'integer', 'min:0'],
            'post_days'     => ['nullable', 'integer', 'min:0'],
            'roles'         => ['required', 'array', 'min:1'],
            'roles.*'       => ['integer', 'exists:roles,id'],
            'customer_type_id' => ['nullable', 'integer', 'exists:membership_types,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'          => 'The discount name is required.',
            'type.required'          => 'The discount type is required.',
            'type.in'                => 'The discount type must be Fixed, Percentage, or Configurable.',
            'discount_type.required' => 'The discount category is required.',
            'start.required'         => 'The start date is required.',
            'end.required'           => 'The end date is required.',
            'end.after_or_equal'     => 'The end date must be after or equal to the start date.',
            'roles.required'         => 'At least one role is required.',
            'roles.min'              => 'At least one role must be selected.',
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
