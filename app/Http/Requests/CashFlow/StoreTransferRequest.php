<?php

namespace App\Http\Requests\CashFlow;

use App\Rules\GoogleDriveUrlRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->can('cashflow_transfer_create');
    }

    public function rules(): array
    {
        return [
            'transfer_date' => 'required|date|before_or_equal:today',
            'amount' => 'required|numeric|min:1|max:99999999|integer',
            'from_pool_id' => 'required|exists:cash_pools,id',
            'to_pool_id' => 'required|exists:cash_pools,id|different:from_pool_id',
            'method' => 'required|in:physical_cash,bank_deposit',
            'reference_no' => 'nullable|string|max:100',
            'attachment_url' => ['required', 'string', 'max:500', new GoogleDriveUrlRule],
            'description' => 'nullable|string|max:50',
        ];
    }

    public function messages(): array
    {
        return [
            'to_pool_id.different' => 'Source and destination pools must be different.',
            'attachment_url.required' => 'Transfer receipt/attachment is required.',
                    ];
    }
}
