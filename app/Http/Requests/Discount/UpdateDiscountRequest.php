<?php

declare(strict_types=1);

namespace App\Http\Requests\Discount;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

final class UpdateDiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('discounts.edit') ?? false;
    }

    public function rules(): array
    {
        $isConfigurable = $this->input('type') === 'Configurable';

        $rules = [
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
            'roles'         => [$isConfigurable ? 'nullable' : 'required', 'array'],
            'roles.*'       => ['integer', 'exists:roles,id'],
            'customer_type_id' => ['nullable', 'integer', 'exists:membership_types,id'],
        ];

        // Configurable update goes through `Discounts::updateConfigurableDiscount`,
        // which rebuilds GET-side rows from `edit_disc_type[]` + `configurable_amount[]`.
        // Tighten those here so the runtime preview math (which branches on
        // discount_type) only ever sees enum-clean values.
        if ($isConfigurable) {
            $editSessions = $this->input('edit_sessions', []);
            foreach ($editSessions as $key => $_value) {
                $rules["edit_sessions.{$key}"]   = ['required', 'integer', 'min:1'];
                $rules["edit_disc_type.{$key}"]  = ['required', 'string', 'in:complimentory,fixed,percentage'];

                $editType = $this->input("edit_disc_type.{$key}");
                if ($editType === 'percentage') {
                    $rules["configurable_amount.{$key}"] = ['required', 'numeric', 'between:0,100'];
                } elseif ($editType === 'fixed') {
                    $rules["configurable_amount.{$key}"] = ['required', 'numeric', 'min:0'];
                }
            }
        }

        return $rules;
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
