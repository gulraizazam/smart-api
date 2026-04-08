<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Order;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_mode' => 'required|string',
            'product_id' => 'required|array|min:1',
            'product_id.*' => 'required|integer|exists:products,id',
            'quantity' => 'required|array|min:1',
            'quantity.*' => 'required|integer|min:1',
            'location_id' => 'required',
            'grand_total' => 'required|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'payment_mode.required' => 'Payment method is required.',
            'product_id.required' => 'Please select any product',
            'quantity.*.min' => 'Quantity must be greater than 0',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'status' => false,
            'message' => $validator->errors()->first(),
            'data' => $validator->errors(),
        ], (int) config('constants.api_status.success', 200)));
    }
}
