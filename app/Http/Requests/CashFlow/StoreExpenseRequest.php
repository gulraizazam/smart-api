<?php

declare(strict_types=1);
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
            'expense_date' => 'required|date|before_or_equal:today|after_or_equal:' . now()->subDays(7)->toDateString(),
            'amount' => 'required|numeric|min:1|max:99999999|integer',
            'category_id' => 'required|exists:expense_categories,id',
            'paid_from_pool_id' => 'nullable|exists:cash_pools,id',
            'for_branch_id' => 'nullable|exists:locations,id',
            'is_for_general' => 'nullable|boolean',
            'payment_method_id' => 'required|exists:payment_modes,id',
            'vendor_id' => 'nullable|exists:cashflow_vendors,id',
            'staff_id' => 'nullable|exists:users,id',
            'description' => 'required|string|min:3|max:100',
            'reference_no' => 'nullable|string|max:100',
            // Both attachment fields are optional individually. Cash payments still
            // need *some* receipt — that cross-field rule is enforced in
            // withValidator() so URL or image satisfies it.
            'attachment_url' => ['nullable', 'string', 'max:500', new GoogleDriveUrlRule],
            'attachment_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120'],
            'notes' => 'nullable|string|max:1000',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // For Branch must be selected or General checked (Sec 5.3/26.12)
            if (!$this->input('for_branch_id') && !$this->input('is_for_general')) {
                $validator->errors()->add('for_branch_id', 'Please select a branch or mark as General / Company-wide.');
            }

            // Must have either a pool or a staff member
            if (!$this->input('paid_from_pool_id') && !$this->input('staff_id')) {
                $validator->errors()->add('paid_from_pool_id', 'Please select a cash pool or a staff member.');
            }

            // Cash payments still need a receipt — accept either an uploaded
            // image or a Google Drive URL (Sec 5.5).
            if ($this->isCashPayment()
                && empty($this->input('attachment_url'))
                && !$this->hasFile('attachment_image')) {
                $validator->errors()->add(
                    'attachment_image',
                    'Attachment is mandatory for cash expenses — upload an image or paste a Google Drive URL.'
                );
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
            'attachment_image.image' => 'Attachment must be an image (JPG, PNG, GIF, or WEBP).',
            'attachment_image.mimes' => 'Allowed image types: JPG, JPEG, PNG, GIF, WEBP.',
            'attachment_image.max' => 'Image must be 5 MB or smaller.',
        ];
    }

    /**
     * Check if the selected payment method is Cash.
     */
    private function isCashPayment(): bool
    {
        $pmId = $this->input('payment_method_id');
        if (!$pmId) {
            return false;
        }

        $pm = \App\Models\PaymentModes::find($pmId);

        return $pm && str_contains(strtolower($pm->name), 'cash');
    }
}
