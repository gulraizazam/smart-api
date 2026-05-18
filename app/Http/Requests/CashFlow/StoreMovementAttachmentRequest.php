<?php

declare(strict_types=1);

namespace App\Http\Requests\CashFlow;

use App\Models\CashFlow\MovementAttachment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Validation for `POST /api/cashflow/movements/staff-attachments`.
 *
 * Mirrors the pool-side {@see StoreCashTransferAttachmentRequest} —
 * file rules + optional pre-bind to a specific (movement_kind,
 * movement_id) pair. Almost all real uploads come in as orphan
 * (both fields NULL) and get bound on form submit.
 */
class StoreMovementAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        return $user->can('cashflow_staff_advance_create')
            || $user->can('cashflow_staff_return_create')
            || $user->can('cashflow_staff_transfer_create')
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
            // Optional pre-bind (edit flow — not used by the create form,
            // which leaves both NULL and binds on submit).
            'movement_kind' => [
                'nullable',
                Rule::in([
                    MovementAttachment::KIND_STAFF_ADVANCE,
                    MovementAttachment::KIND_STAFF_RETURN,
                    MovementAttachment::KIND_STAFF_TRANSFER,
                ]),
                'required_with:movement_id',
            ],
            'movement_id' => [
                'nullable',
                'integer',
                'required_with:movement_kind',
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
