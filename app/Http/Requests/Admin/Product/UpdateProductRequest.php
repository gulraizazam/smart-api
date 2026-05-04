<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Product;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // The route signature is `update(Request, int $id, int $detail)` so
        // the product id rides on the URL — pull it from the route to scope
        // the SKU uniqueness check to "any other product than this one".
        $productId = (int) $this->route('id');

        return [
            'name' => 'sometimes|required|string|max:255',
            'brand_id' => 'sometimes|required|integer|exists:brands,id',
            'sku' => [
                'sometimes',
                'required',
                'string',
                Rule::unique('products', 'sku')->ignore($productId),
            ],
            'sale_price' => 'nullable|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'sku.unique' => 'This SKU is already in use by another product.',
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
