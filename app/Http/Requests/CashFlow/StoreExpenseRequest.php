<?php

namespace App\Http\Requests\CashFlow;

use App\Rules\GoogleDriveUrlRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->can('cashflow_expense_create');
    }

    public function rules(): array
    {
        $rules = [
            'expense_date' => 'required|date|before_or_equal:today',
            'amount' => 'required|numeric|min:1|max:99999999|integer',
            'category_id' => 'required|exists:expense_categories,id',
            'paid_from_pool_id' => 'required|exists:cash_pools,id',
            'for_branch_id' => 'nullable|exists:locations,id',
            'is_for_general' => 'nullable|boolean',
            'payment_method_id' => 'required|exists:payment_modes,id',
            'vendor_id' => 'nullable|exists:cashflow_vendors,id',
            'staff_id' => 'nullable|exists:users,id',
            'description' => 'required|string|min:3|max:50',
            'reference_no' => 'nullable|string|max:100',
            'attachment_url' => ['nullable', 'string', 'max:500', new GoogleDriveUrlRule],
            'notes' => 'nullable|string|max:1000',
        ];

        // Cash payment method requires attachment (Sec 5.5)
        if ($this->isCashPayment()) {
            $rules['attachment_url'] = ['required', 'string', 'max:500', new GoogleDriveUrlRule];
        }

        return $rules;
    }

    /**
     * Check if the selected payment method is Cash.
     */
    private function isCashPayment(): bool
    {
        $pmId = $this->input('payment_method_id');
        if (!$pmId) return false;

        $pm = \App\Models\PaymentModes::find($pmId);
        return $pm && str_contains(strtolower($pm->name), 'cash');
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // For Branch must be selected or General checked (Sec 5.3/26.12)
            if (!$this->input('for_branch_id') && !$this->input('is_for_general')) {
                $validator->errors()->add('for_branch_id', 'Please select a branch or mark as General / Company-wide.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'expense_date.before_or_equal' => 'Expense date cannot be in the future.',
            'amount.min' => 'Amount must be at least 1.',
            'amount.integer' => 'Amount must be a whole number (no decimals).',
            'description.min' => 'Description must be at least 3 characters.',
            'attachment_url.required' => 'Attachment is mandatory for cash expenses.',
        ];
    }
}
