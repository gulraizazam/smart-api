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
        return [
            'expense_date' => 'required|date|before_or_equal:today',
            'amount' => 'required|numeric|min:0.01|max:99999999.99',
            'category_id' => 'required|exists:expense_categories,id',
            'paid_from_pool_id' => 'required|exists:cash_pools,id',
            'for_branch_id' => 'nullable|exists:locations,id',
            'is_for_general' => 'nullable|boolean',
            'payment_method_id' => 'required|exists:payment_modes,id',
            'vendor_id' => 'nullable|exists:cashflow_vendors,id',
            'staff_id' => 'nullable|exists:users,id',
            'description' => 'required|string|min:3|max:500',
            'reference_no' => 'nullable|string|max:100',
            'attachment_url' => ['nullable', 'string', 'max:500', new GoogleDriveUrlRule],
            'notes' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'expense_date.before_or_equal' => 'Expense date cannot be in the future.',
            'amount.min' => 'Amount must be at least 0.01.',
            'description.min' => 'Description must be at least 3 characters.',
        ];
    }
}
