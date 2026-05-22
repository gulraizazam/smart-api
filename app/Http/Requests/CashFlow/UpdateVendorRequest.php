<?php

declare(strict_types=1);
namespace App\Http\Requests\CashFlow;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateVendorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->can('cashflow.vendor.edit');
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'contact_person' => 'required|string|max:255',
            'phone' => 'required|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:1000',
            'payment_terms' => 'nullable|in:upfront,net_7,net_15,net_30,custom',
            'category' => 'nullable|string|max:255',
            'category_id' => ['required', 'integer', Rule::exists('expense_categories', 'id')->where('account_id', (int) Auth::user()->account_id)->whereNull('deleted_at')],
            'opening_balance' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
            'is_active' => 'nullable|boolean',
        ];
    }
}
