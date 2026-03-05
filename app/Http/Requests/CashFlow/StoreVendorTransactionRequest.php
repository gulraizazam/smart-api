<?php

namespace App\Http\Requests\CashFlow;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreVendorTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->can('cashflow_vendor_transaction');
    }

    public function rules(): array
    {
        return [
            'vendor_id' => 'required|exists:cashflow_vendors,id',
            'type' => 'required|in:purchase,payment',
            'amount' => 'required|numeric|min:0.01|max:99999999.99',
            'description' => 'nullable|string|max:500',
            'reference_no' => 'nullable|string|max:100',
        ];
    }
}
