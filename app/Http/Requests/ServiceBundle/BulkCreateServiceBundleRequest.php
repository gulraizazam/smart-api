<?php

declare(strict_types=1);

namespace App\Http\Requests\ServiceBundle;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Gate;

class BulkCreateServiceBundleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('bundles.bulk_create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'service_ids'         => 'required|array|min:1',
            'service_ids.*'       => 'required|integer|exists:services,id',
            'sessions'            => 'required|integer|min:1|max:999',
            'discount_percentage' => 'required|numeric|min:0|max:100',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'service_ids.required'   => 'Please select at least one service.',
            'service_ids.min'        => 'Please select at least one service.',
            'service_ids.*.exists'   => 'One or more selected services do not exist.',
            'sessions.required'      => 'Number of sessions is required.',
            'sessions.min'           => 'Sessions must be at least 1.',
            'discount_percentage.required' => 'Discount percentage is required.',
            'discount_percentage.max'      => 'Discount percentage cannot exceed 100.',
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
