<?php

declare(strict_types=1);
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
            'expense_date' => 'required|date|before_or_equal:today|after_or_equal:' . now()->subDays(7)->toDateString(),
            'amount' => 'required|numeric|min:1|max:99999999|integer',
            'category_id' => 'required|exists:expense_categories,id',
            'paid_from_pool_id' => 'nullable|exists:cash_pools,id',
            'payment_method_id' => 'required|exists:payment_modes,id',
            'for_branch_id' => 'nullable|exists:locations,id',
            'is_for_general' => 'nullable|boolean',
            'vendor_id' => 'nullable',
            'staff_id' => 'nullable|exists:users,id',
            'description' => 'required|string|min:3|max:100',
            'reference_no' => 'nullable|string|max:100',
            // URL is now always optional — operators can attach an uploaded
            // image instead of (or alongside) a Drive link.
            'attachment_url' => ['nullable', 'string', 'max:500', new GoogleDriveUrlRule],
            'attachment_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120'],
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

            // Must have either a pool or a staff member
            if (!$this->input('paid_from_pool_id') && !$this->input('staff_id')) {
                $validator->errors()->add('paid_from_pool_id', 'Please select a cash pool or a staff member.');
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
            'paid_from_pool_id.exists' => 'The selected pool is invalid.',
            'payment_method_id.required' => 'Payment Method is required.',
            'description.required' => 'Description is required.',
            'description.min' => 'Description must be at least 3 characters.',
            'edit_reason.required' => 'A reason for editing is required.',
            'edit_reason.min' => 'Edit reason must be at least 5 characters.',
            'attachment_image.image' => 'Attachment must be an image (JPG, PNG, GIF, or WEBP).',
            'attachment_image.mimes' => 'Allowed image types: JPG, JPEG, PNG, GIF, WEBP.',
            'attachment_image.max' => 'Image must be 5 MB or smaller.',
        ];
    }
}
