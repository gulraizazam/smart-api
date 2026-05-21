<?php

declare(strict_types=1);
namespace App\Http\Requests\CashFlow;

use App\Enums\ExpenseStatus;
use App\Models\CashFlow\Expense;
use App\Rules\GoogleDriveUrlRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();
        if (! $user) return false;
        // Standard edit gate — admins / editors get through here.
        if ($user->can('cashflow.expense.edit')) {
            return true;
        }
        // Creator escape hatch (2026-05-14): the row's creator can
        // edit their OWN payment when it's been rejected OR is in
        // pending re-approval. Rejected → edit moves it to Pending;
        // Pending stays editable in case the creator wants to tweak
        // before admin sign-off. Anyone else is still gated on the
        // global slug.
        $id = $this->route('id');
        if (! $id) return false;
        $expense = Expense::forAccount((int) $user->account_id)->find($id);
        return $expense
            && in_array($expense->status, [ExpenseStatus::Rejected, ExpenseStatus::Pending], true)
            && (int) $expense->created_by === (int) $user->id;
    }

    public function rules(): array
    {
        // The 7-day backdating floor is enforced on CREATE only. Edits are
        // admin overrides with a mandatory `edit_reason` + full audit trail,
        // so genuinely old rows (legacy imports, payments needing a
        // correction weeks later, etc.) must still be editable — otherwise
        // the user gets stuck unable to fix a typo on a 3-week-old row.
        //
        // Tenant scope + soft-delete exclusion on every FK. See
        // StoreExpenseRequest for the full explanation.
        $accountId = (int) Auth::user()->account_id;
        $forAccountAlive = fn (string $table) => Rule::exists($table, 'id')
            ->where('account_id', $accountId)
            ->whereNull('deleted_at');

        return [
            'expense_date' => 'required|date|before_or_equal:today',
            'amount' => 'required|numeric|min:1|max:99999999|integer',
            'category_id' => ['required', $forAccountAlive('expense_categories')],
            'paid_from_pool_id' => ['nullable', $forAccountAlive('cash_pools')],
            'payment_method_id' => ['required', $forAccountAlive('payment_modes')],
            'for_branch_id' => ['nullable', $forAccountAlive('locations')],
            'is_for_general' => 'nullable|boolean',
            'vendor_id' => ['nullable', $forAccountAlive('cashflow_vendors')],
            // staff_id MUST point to a non-patient, non-deleted user.
            'staff_id' => ['nullable', Rule::exists('users', 'id')
                ->where('account_id', $accountId)
                ->whereNull('deleted_at')
                ->whereNot('user_type_id', (int) \Illuminate\Support\Facades\Config::get('constants.patient_id', 3))],
            'description' => 'required|string|min:3|max:100',
            'reference_no' => 'nullable|string|max:100',
            'attachment_url' => ['nullable', 'string', 'max:500', new GoogleDriveUrlRule],
            'notes' => 'nullable|string|max:1000',
            'edit_reason' => 'required|string|min:5|max:500',
            // New uploaded attachments — same shape + limits as the
            // create request. Service binds them to this expense.
            'attachment_ids' => 'sometimes|array|max:10',
            'attachment_ids.*' => 'integer|exists:expense_attachments,id',
            // Explicit signal that the SPA wants to drop the legacy URL
            // (the "Remove" button on the legacy-link badge). Without
            // this, an empty attachment_url is interpreted as "leave it
            // alone" rather than "clear it".
            'remove_attachment_url' => 'sometimes|boolean',
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
        ];
    }
}
