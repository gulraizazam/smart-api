<?php

declare(strict_types=1);

namespace App\Http\Requests\Consultancy;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreConsultancyInvoiceRequest extends FormRequest
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
            'appointment_id' => 'required|exists:appointments,id',
            'price' => 'nullable|numeric|min:0',
            'amount_create' => 'nullable|numeric|min:0',
            'tax_create' => 'nullable|numeric|min:0',
            'cash' => 'nullable|numeric|min:0',
            'settle' => 'nullable|numeric|min:0',
            'payment_mode_id' => 'nullable|integer',
            'discount_id' => 'nullable|exists:discounts,id',
            'discount_type' => 'nullable|string|max:50',
            'discount_value' => 'nullable|numeric|min:0',
            'tax_treatment_type_id' => 'nullable|integer',
            'is_exclusive' => 'nullable|boolean',
        ];
    }

    /**
     * @return array<string, string>
     */
    #[\Override]
    public function messages(): array
    {
        return [
            'appointment_id.required' => 'Appointment is required to generate an invoice.',
            'appointment_id.exists' => 'Invalid appointment selected.',
            'price.numeric' => 'Price must be a number.',
            'price.min' => 'Price cannot be negative.',
        ];
    }
}
