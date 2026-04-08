<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Returns HTTP 422 on validation failure — matching makePackagesServicesData() behavior.
 */
class MakePackageServicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bundle_id'      => 'required|integer|exists:services,id',
            'location_id'    => 'required|integer|exists:locations,id',
            'user_id'        => 'required|integer|exists:users,id',
            'random_id'      => 'required|string',
            'net_amount'     => 'required|numeric|min:0',
            'sold_by'        => 'nullable|integer|exists:users,id',
            'discount_id'    => 'nullable|integer|exists:discounts,id',
            'discount_price' => 'nullable|numeric|min:0',
            'discount_type'  => 'nullable|string',
            'is_exclusive'   => 'nullable|in:0,1',
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'data'    => ['errors' => $validator->errors()],
                'errors'  => [],
            ], 422)
        );
    }
}
