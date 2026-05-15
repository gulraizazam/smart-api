<?php

declare(strict_types=1);

namespace App\Http\Requests\CashFlow;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * Validation for `POST /api/cashflow/expenses/attachments`.
 *
 * Authorises any user who can create or edit a payment (or has the
 * blanket `cashflow_manage` slug). The `expense_id` field is optional
 * because the SPA uploads files while the user is still filling the
 * form — the attachment is bound to the expense at form-submit time.
 *
 * Defense-in-depth: this validator pins the *declared* MIME header and
 * the file extension, but the actual controller also sniffs the magic
 * bytes via `finfo_open(FILEINFO_MIME_TYPE)` — both layers have to
 * agree before the file is accepted, so renaming an .exe to .pdf gets
 * rejected even though the extension passes here.
 */
class StoreExpenseAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        return $user->can('cashflow_expense_create')
            || $user->can('cashflow_expense_edit')
            || $user->can('cashflow_manage');
    }

    public function rules(): array
    {
        return [
            // 10 MB cap (10240 KB); `mimes` checks the extension,
            // `mimetypes` checks the declared upload header. Magic-byte
            // check runs in the controller.
            'file' => [
                'required',
                'file',
                'max:10240',
                'mimes:pdf,jpg,jpeg,png,heic,heif,webp',
                'mimetypes:application/pdf,image/jpeg,image/png,image/heic,image/heif,image/webp',
            ],
            // expense_id (when supplied) MUST belong to the caller's
            // tenant. The bare `exists:expenses,id` rule accepts any
            // expense id — without scoping, a user could upload a file
            // and bind it to another tenant's expense ID, creating a
            // dangling cross-tenant attachment row.
            'expense_id' => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('expenses', 'id')
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
