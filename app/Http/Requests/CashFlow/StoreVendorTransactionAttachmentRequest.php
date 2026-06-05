<?php

declare(strict_types=1);

namespace App\Http\Requests\CashFlow;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * Validation for `POST /api/cashflow/vendors/attachments`.
 *
 * Exact mirror of {@see StoreExpenseAttachmentRequest}. Authorises any
 * user who can record a vendor transaction (or has `cashflow_manage`).
 * `vendor_transaction_id` is optional — the SPA uploads while the
 * purchase/deliver form is still being filled and binds the returned
 * IDs at submit time. Same defense-in-depth: declared MIME + extension
 * here, magic-byte sniff in the controller.
 */
class StoreVendorTransactionAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        // Attachments are added both while *recording* a purchase (create)
        // and while *editing* an existing one — so honour the edit grant
        // too. Without it a user who can edit an order but not create one
        // would be 403'd trying to attach an invoice mid-edit. Mirrors the
        // expense attachment gate (which accepts `cashflow.expense.edit`).
        return $user->can('cashflow.vendor.transaction.create')
            || $user->can('cashflow.vendor.transaction.edit')
            || $user->can('cashflow.vendor.manage')
            || $user->can('cashflow.manage');
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:10240',
                'mimes:pdf,jpg,jpeg,png,heic,heif,webp',
                'mimetypes:application/pdf,image/jpeg,image/png,image/heic,image/heif,image/webp',
            ],
            // vendor_transaction_id (when supplied) MUST belong to the
            // caller's tenant — same cross-tenant binding defense as the
            // expense attachment request.
            'vendor_transaction_id' => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('vendor_transactions', 'id')
                ->where('account_id', (int) Auth::user()->account_id)
                ->whereNull('deleted_at')],
        ];
    }

    public function messages(): array
    {
        return [
            'file.max' => 'File must be 10 MB or smaller.',
            'file.mimes' => 'Only PDF, JPG, PNG, HEIC, and WEBP files are allowed.',
            'file.mimetypes' => 'File type does not match its extension.',
        ];
    }
}
