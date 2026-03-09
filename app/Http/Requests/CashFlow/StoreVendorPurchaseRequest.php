<?php

namespace App\Http\Requests\CashFlow;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreVendorPurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->can('cashflow_vendor_transaction');
    }

    public function rules(): array
    {
        return [
            'amount' => 'required|numeric|min:1|max:99999999|integer',
            'description' => 'required|string|min:3|max:500',
            'reference_no' => 'nullable|string|max:100',
            'attachment_url' => 'required|url|max:500',
            'transaction_date' => 'required|date|before_or_equal:today|after_or_equal:' . now()->subDays(7)->toDateString(),
            'for_branch_id' => 'nullable',
            'is_for_general' => 'nullable|boolean',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (!$this->input('for_branch_id') && !$this->input('is_for_general')) {
                $validator->errors()->add('for_branch_id', 'Please select a branch or General / Company-wide.');
            }
        });
    }
}
