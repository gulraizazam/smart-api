<?php

declare(strict_types=1);

namespace App\Http\Requests\ServiceBundle;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Gate;

class StoreServiceBundleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('bundles.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'service_id'          => 'required|integer|exists:services,id',
            'sessions'            => 'required|integer|min:1|max:999',
            'price'               => 'required|numeric|min:0',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'service_id.required' => 'Please select a service.',
            'service_id.exists'   => 'The selected service does not exist.',
            'sessions.required'   => 'Number of sessions is required.',
            'sessions.min'        => 'Sessions must be at least 1.',
            'price.required'      => 'Bundle price is required.',
            'price.min'           => 'Bundle price must be at least 0.',
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
            'message' => 'You are not authorized to create bundles.',
            'data'    => null,
            'errors'  => [],
        ], 403));
    }
}
