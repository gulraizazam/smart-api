<?php

declare(strict_types=1);

namespace App\Http\Requests\Discount;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

final class StoreConfigurableDiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('discounts_create') ?? false;
    }

    public function rules(): array
    {
        $rules = [
            'name'         => ['required', 'string', 'max:255'],
            'type'         => ['required', 'string', 'in:Configurable'],
            'start'        => ['required', 'date'],
            'end'          => ['required', 'date', 'after_or_equal:start'],
            'sessions_buy' => ['required', 'integer', 'min:1'],
            'base_service' => ['required'],
            'buy_mode'     => ['nullable', 'string', 'in:service,category'],
            'active'       => ['nullable', 'in:0,1'],
            'slug'         => ['nullable', 'string', 'max:100'],
            'discount_type'    => ['nullable', 'string', 'in:Treatment,Consultancy'],
            'customer_type_id' => ['nullable', 'integer', 'exists:membership_types,id'],
            'roles'        => ['nullable', 'array'],
            'roles.*'      => ['integer', 'exists:roles,id'],
            'sessions'     => ['required', 'array', 'min:1'],
        ];

        $sessions = $this->input('sessions', []);
        foreach ($sessions as $key => $value) {
            $rules["sessions.{$key}"]  = ['required', 'integer', 'min:1'];
            $rules["disc_type.{$key}"] = ['required', 'string'];

            $sameService = $this->input("same_service.{$key}");
            if (!$sameService) {
                $rules["services_name.{$key}"] = ['required', 'integer', 'exists:services,id'];
            }
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'name.required'         => 'The discount name is required.',
            'start.required'        => 'The start date is required.',
            'end.required'          => 'The end date is required.',
            'end.after_or_equal'    => 'The end date must be after or equal to the start date.',
            'sessions_buy.required' => 'The number of buy sessions is required.',
            'base_service.required' => 'The base service is required.',
            'sessions.required'     => 'At least one GET service session is required.',
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
            ], 401)
        );
    }
}
