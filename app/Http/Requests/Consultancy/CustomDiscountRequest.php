<?php

declare(strict_types=1);

namespace App\Http\Requests\Consultancy;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class CustomDiscountRequest extends FormRequest
{
    #[\Override]
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * @return array<string, string>
     */
    #[\Override]
    public function rules(): array
    {
        return [
            'location_id' => 'required|exists:locations,id',
            'discount_id' => 'required|exists:discounts,id',
            'discount_type' => 'required|string',
            'discount_value' => 'required|numeric|min:0',
            'price' => 'required|numeric|min:0.01',
            'tax_treatment_type_id' => 'nullable|integer',
            'is_exclusive_consultancy' => 'nullable|in:0,1',
        ];
    }

    /**
     * @return array<string, string>
     */
    #[\Override]
    public function messages(): array
    {
        return [
            'discount_id.required' => 'Discount selection is required.',
            'discount_value.required' => 'Discount value is required.',
            'price.required' => 'Base price is required.',
            'price.min' => 'Base price must be greater than zero.',
        ];
    }
}
