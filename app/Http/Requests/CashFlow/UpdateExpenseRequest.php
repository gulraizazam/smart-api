<?php

namespace App\Http\Requests\CashFlow;

use App\Rules\GoogleDriveUrlRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->can('cashflow_expense_edit');
    }

    public function rules(): array
    {
        return [
            'expense_date' => 'required|date|before_or_equal:today',
            'amount' => 'required|numeric|min:1|max:99999999|integer',
            'category_id' => 'required|exists:expense_categories,id',
            'paid_from_pool_id' => 'required|exists:cash_pools,id',
            'payment_method_id' => 'required|exists:payment_modes,id',
            'for_branch_id' => 'nullable|exists:locations,id',
            'is_for_general' => 'nullable|boolean',
            'vendor_id' => 'nullable',
            'description' => 'required|string|min:3|max:50',
            'reference_no' => 'nullable|string|max:100',
            'attachment_url' => ['nullable', 'string', 'max:500', new GoogleDriveUrlRule],
            'notes' => 'nullable|string|max:1000',
            'edit_reason' => 'required|string|min:5|max:500',
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

    public function messages(): array
    {
        return [
            'expense_date.required' => 'Date is required.',
            'expense_date.before_or_equal' => 'Expense date cannot be in the future.',
            'amount.required' => 'Amount is required.',
            'amount.min' => 'Amount must be at least 1.',
            'amount.integer' => 'Amount must be a whole number (no decimals).',
            'category_id.required' => 'Category is required.',
            'paid_from_pool_id.required' => 'Paid From Pool is required.',
            'payment_method_id.required' => 'Payment Method is required.',
            'description.required' => 'Description is required.',
            'description.min' => 'Description must be at least 3 characters.',
            'edit_reason.required' => 'A reason for editing is required.',
            'edit_reason.min' => 'Edit reason must be at least 5 characters.',
        ];
    }
}
