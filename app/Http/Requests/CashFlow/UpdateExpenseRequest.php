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
            'expense_date' => 'nullable|date|before_or_equal:today',
            'amount' => 'nullable|numeric|min:1|max:99999999|integer',
            'category_id' => 'nullable|exists:expense_categories,id',
            'paid_from_pool_id' => 'nullable|exists:cash_pools,id',
            'payment_method_id' => 'nullable|exists:payment_modes,id',
            'for_branch_id' => 'nullable|exists:locations,id',
            'is_for_general' => 'nullable|boolean',
            'vendor_id' => 'nullable',
            'description' => 'nullable|string|min:3|max:500',
            'reference_no' => 'nullable|string|max:100',
            'attachment_url' => ['nullable', 'string', 'max:500', new GoogleDriveUrlRule],
            'notes' => 'nullable|string|max:1000',
            'edit_reason' => 'required|string|min:5|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'edit_reason.required' => 'A reason for editing is required.',
            'edit_reason.min' => 'Edit reason must be at least 5 characters.',
        ];
    }
}
