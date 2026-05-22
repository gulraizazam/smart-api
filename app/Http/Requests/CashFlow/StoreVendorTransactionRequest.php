<?php

declare(strict_types=1);
namespace App\Http\Requests\CashFlow;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreVendorTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->can('cashflow.vendor.transaction.create');
    }

    public function rules(): array
    {
        return [
            'vendor_id' => ['required', Rule::exists('cashflow_vendors', 'id')->where('account_id', (int) Auth::user()->account_id)->whereNull('deleted_at')],
            'type' => 'required|in:purchase,payment',
            'amount' => 'required|numeric|min:0.01|max:99999999.99',
            'description' => 'nullable|string|max:500',
            'reference_no' => 'nullable|string|max:100',
        ];
    }
}
