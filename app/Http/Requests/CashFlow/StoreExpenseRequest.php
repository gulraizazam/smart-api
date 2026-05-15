<?php

declare(strict_types=1);
namespace App\Http\Requests\CashFlow;

use App\Rules\GoogleDriveUrlRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->can('cashflow_expense_create');
    }

    public function rules(): array
    {
        // Every foreign-key field is scoped to the caller's tenant AND
        // excludes soft-deleted rows. Laravel's `Rule::exists()` queries
        // the table directly (no model scope), so without the explicit
        // `deleted_at IS NULL` guard a soft-deleted pool / vendor /
        // category would still satisfy the rule. The FK constraint at
        // the DB level happily resolves to a deleted row — the result
        // is an expense bound to a non-existent (from the UI's pov)
        // pool, breaking reconciliation.
        $accountId = (int) Auth::user()->account_id;
        $forAccountAlive = fn (string $table) => Rule::exists($table, 'id')
            ->where('account_id', $accountId)
            ->whereNull('deleted_at');

        $rules = [
            'expense_date' => 'required|date|before_or_equal:today|after_or_equal:' . now()->subDays(7)->toDateString(),
            'amount' => 'required|numeric|min:1|max:99999999|integer',
            'category_id' => ['required', $forAccountAlive('expense_categories')],
            'paid_from_pool_id' => ['nullable', $forAccountAlive('cash_pools')],
            'for_branch_id' => ['nullable', $forAccountAlive('locations')],
            'is_for_general' => 'nullable|boolean',
            'payment_method_id' => ['required', $forAccountAlive('payment_modes')],
            'vendor_id' => ['nullable', $forAccountAlive('cashflow_vendors')],
            // staff_id MUST point to a non-patient, non-deleted user.
            // See above for the deleted_at / type-filter rationale.
            'staff_id' => ['nullable', Rule::exists('users', 'id')
                ->where('account_id', $accountId)
                ->whereNull('deleted_at')
                ->whereNot('user_type_id', (int) \Illuminate\Support\Facades\Config::get('constants.patient_id', 3))],
            'description' => 'required|string|min:3|max:100',
            'reference_no' => 'nullable|string|max:100',
            'attachment_url' => ['nullable', 'string', 'max:500', new GoogleDriveUrlRule],
            'notes' => 'nullable|string|max:1000',
            // New uploaded attachments to bind to this payment. Each id
            // must already exist in `expense_attachments` and belong to
            // the current account — the service layer asserts both.
            // Hard cap: 10 files per expense.
            'attachment_ids' => 'sometimes|array|max:10',
            'attachment_ids.*' => 'integer|exists:expense_attachments,id',
        ];

        // Cash payment method requires SOME proof of payment — either a
        // Google Drive URL (legacy) or at least one uploaded attachment.
        // Custom `withValidator` below enforces the OR; the per-field
        // rules stay nullable.
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

            // Must have either a pool or a staff member
            if (!$this->input('paid_from_pool_id') && !$this->input('staff_id')) {
                $validator->errors()->add('paid_from_pool_id', 'Please select a cash pool or a staff member.');
            }

            // Cash payment method requires SOME proof of payment — either
            // a legacy URL or at least one uploaded attachment.
            if ($this->isCashPayment()) {
                $hasUrl = !empty($this->input('attachment_url'));
                $hasFiles = is_array($this->input('attachment_ids')) && count($this->input('attachment_ids')) > 0;
                if (!$hasUrl && !$hasFiles) {
                    $validator->errors()->add('attachment_ids', 'Cash payments need at least one receipt — upload a file or paste a Google Drive link.');
                }
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
