<?php

declare(strict_types=1);
namespace App\Http\Requests\CashFlow;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreVendorPurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Edit uses route param txId, create does not
        if ($this->route('txId')) {
            return Auth::user()->can('cashflow_vendor_transaction_edit');
        }
        return Auth::user()->can('cashflow_vendor_transaction');
    }

    public function rules(): array
    {
        $isDelivered = $this->input('status', 'delivered') === 'delivered';
        // Edit mode is signalled by the `txId` route param. On edit the user
        // must supply an `edit_reason` so the audit log captures why the row
        // changed (mirroring the contract on Expenses + Transfers edits).
        $isEdit = (bool) $this->route('txId');

        return [
            'amount'           => 'required|numeric|min:1|max:99999999|integer',
            'description'      => 'required|string|min:3|max:100',
            'reference_no'     => 'nullable|string|max:100',
            // Legacy single Drive URL — retired on the SPA but kept
            // writable for backward compat. When delivered it's only
            // required if no uploaded attachments are supplied.
            'attachment_url'   => $isDelivered ? 'required_without:attachment_ids|nullable|url|max:500' : 'nullable|url|max:500',
            // R2-backed multi-file uploads (mirror of expense attachments).
            'attachment_ids'   => 'nullable|array',
            'attachment_ids.*' => 'integer',
            'transaction_date' => 'required|date|before_or_equal:today|after_or_equal:' . now()->subDays(7)->toDateString(),
            'for_branch_id'    => 'nullable',
            'is_for_general'   => 'nullable|boolean',
            'status'           => 'required|in:ordered,delivered',
            'edit_reason'      => $isEdit ? 'required|string|min:5|max:500' : 'nullable|string|max:500',
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
