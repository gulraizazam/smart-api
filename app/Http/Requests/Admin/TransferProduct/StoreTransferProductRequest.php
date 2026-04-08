<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\TransferProduct;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreTransferProductRequest extends FormRequest
{
    #[\Override]
    public function authorize(): bool
    {
        return true;
    }

    #[\Override]
    public function rules(): array
    {
        return [
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'required|numeric|min:1',
            'from_location_id' => 'required',
            'to_location_id' => 'required',
        ];
    }

    #[\Override]
    public function messages(): array
    {
        return [
            'product_id.required' => 'Product is required.',
            'quantity.required' => 'Quantity is required.',
            'quantity.min' => 'Quantity must be at least 1.',
            'from_location_id.required' => 'Source location is required.',
            'to_location_id.required' => 'Destination location is required.',
        ];
    }

    #[\Override]
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'status' => false,
            'message' => $validator->errors()->first(),
            'data' => $validator->errors(),
        ], (int) config('constants.api_status.success', 200)));
    }
}
