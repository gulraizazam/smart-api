<?php

declare(strict_types=1);

namespace App\Http\Requests\Consultancy;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class CalculateConsultancyPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'appointment_id' => 'required|exists:appointments,id',
            'location_id' => 'required|exists:locations,id',
            'discount_id' => 'nullable|exists:discounts,id',
            'price_for_calculation' => 'required|numeric|min:0',
            'tax_treatment_type_id' => 'nullable|integer',
            'is_exclusive_consultancy' => 'nullable|in:0,1',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'appointment_id.required' => 'Appointment ID is required.',
            'location_id.required' => 'Location is required for price calculation.',
            'price_for_calculation.required' => 'Base price is required for calculation.',
        ];
    }
}
