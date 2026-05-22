<?php

declare(strict_types=1);

namespace App\Http\Requests\Bundle;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Gate;

class StoreBundleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('packages.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name'                 => 'required|string|max:255',
            'price'                => 'required|numeric|min:0',
            'pricing_mode'         => 'nullable|in:discount,net',
            'start'                => 'nullable|date',
            'end'                  => 'nullable|date|after_or_equal:start',
            'apply_discount'       => 'nullable|boolean',
            'tax_treatment_type_id' => 'nullable|integer|exists:tax_treatment_type,id',
            'service_id'           => 'required|array|min:1',
            'service_id.*'         => 'required|integer|exists:services,id',
            'service_price'        => 'required|array|min:1',
            'service_price.*'      => 'required|numeric|min:0',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required'        => 'The package name is required.',
            'price.required'       => 'The package price is required.',
            'price.numeric'        => 'The package price must be a number.',
            'price.min'            => 'The package price must be at least 0.',
            'end.after_or_equal'   => 'The end date must be after or equal to the start date.',
            'service_id.required'  => 'At least one service is required.',
            'service_id.min'       => 'At least one service is required.',
            'service_id.*.exists'  => 'One or more selected services do not exist.',
            'service_price.required' => 'Service prices are required.',
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'status'  => false,
            'message' => $validator->errors()->first(),
            'data'    => null,
            'errors'  => $validator->errors(),
        ], 422));
    }

    protected function failedAuthorization(): never
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'status'  => false,
            'message' => 'You are not authorized to create packages.',
            'data'    => null,
            'errors'  => [],
        ], 403));
    }
}
