<?php

namespace App\Http\Requests\CashFlow;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateVendorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->can('cashflow_vendor_manage');
    }

    public function rules(): array
    {
        return [
            'name' => 'nullable|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:1000',
            'payment_terms' => 'nullable|in:upfront,net_7,net_15,net_30,custom',
            'category' => 'nullable|string|max:255',
            'opening_balance' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
            'is_active' => 'nullable|boolean',
        ];
    }
}
