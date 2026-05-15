<?php

declare(strict_types=1);
namespace App\Http\Requests\CashFlow;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreVendorSuggestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->can('cashflow_vendor_request');
    }

    public function rules(): array
    {
        return [
            'name'           => 'required|string|max:255',
            'contact_person' => 'required|string|max:255',
            'phone'          => 'required|string|max:50',
            'email'          => 'nullable|email|max:255',
            'payment_terms'  => 'required|in:upfront,net_7,net_15,net_30,custom',
            'category_id'    => ['required', 'integer', Rule::exists('expense_categories', 'id')->where('account_id', (int) Auth::user()->account_id)->whereNull('deleted_at')],
            'opening_balance'=> 'nullable|numeric|min:0',
            'address'        => 'nullable|string|max:500',
            'notes'          => 'nullable|string|max:500',
        ];
    }
}
