<?php

declare(strict_types=1);

namespace App\Http\Requests\Discount;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

final class AllocateConfigurableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('discounts.allocate') ?? false;
    }

    public function rules(): array
    {
        return [
            'discount_id' => ['required', 'integer', 'exists:discounts,id'],
            'location_id' => ['required', 'integer', 'exists:locations,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'discount_id.required' => 'The discount is required.',
            'discount_id.exists'   => 'The selected discount does not exist.',
            'location_id.required' => 'The location is required.',
            'location_id.exists'   => 'The selected location does not exist.',
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
