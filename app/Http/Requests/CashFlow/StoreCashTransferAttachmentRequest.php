<?php

declare(strict_types=1);

namespace App\Http\Requests\CashFlow;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Validation for `POST /api/cashflow/movements/attachments`.
 *
 * Mirrors {@see StoreExpenseAttachmentRequest} — the same magic-byte
 * sniff happens controller-side; this layer enforces the declared
 * MIME header + extension + size cap and scopes the optional
 * cash_transfer_id binding to the caller's tenant.
 */
class StoreCashTransferAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        return $user->can('cashflow_transfer_create')
            || $user->can('cashflow_manage');
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
            'cash_transfer_id' => [
                'nullable',
                'integer',
                Rule::exists('cash_transfers', 'id')
                    ->where('account_id', (int) Auth::user()->account_id)
                    ->whereNull('deleted_at'),
            ],
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
