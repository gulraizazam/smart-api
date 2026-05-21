<?php

declare(strict_types=1);

namespace App\Http\Requests\Refund;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

final class StoreRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }

        // Either side is enough — global module refund perm or the
        // patient-card refund perm from the Patients audit.
        return $user->can('refunds.refund') || $user->can('patients_refund_refund');
    }

    public function rules(): array
    {
        // Tenant-scoped + soft-delete-aware FK validation. Hardened
        // 2026-05-15 — previously these were bare `exists:` rules that
        // accepted any tenant's id. The refund flow moves cash, so
        // cross-tenant injection here is a real money primitive.
        //
        // created_at cap: refunds must be no older than 90 days. A
        // refund backdated to last year would distort the books AND
        // bypass the case-settled / fiscal-period accounting. The cap
        // is short enough to limit abuse but long enough for normal
        // late corrections.
        $accountId = (int) ($this->user()?->account_id ?? 0);

        return [
            'refund_amount'   => ['required', 'numeric', 'min:1', 'regex:/^[0-9]+$/'],
            'refund_note'     => ['required', 'string', 'max:1000'],
            'package_id'      => ['required', 'integer', Rule::exists('packages', 'id')->where('account_id', $accountId)->whereNull('deleted_at')],
            'payment_mode_id' => ['nullable', 'integer', Rule::exists('payment_modes', 'id')->where('account_id', $accountId)->whereNull('deleted_at')],
            'created_at'      => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:' . now()->subDays(90)->toDateString(), 'before_or_equal:today'],
            'date_backend'    => ['nullable', 'date_format:Y-m-d'],
            'case_setteled'   => ['nullable', 'in:0,1'],
            'patient_id'      => ['nullable', 'integer', Rule::exists('users', 'id')->where('account_id', $accountId)->whereNull('deleted_at')],
        ];
    }

    public function messages(): array
    {
        return [
            'refund_amount.required'   => 'The refund amount is required.',
            'refund_amount.numeric'    => 'The refund amount must be a number.',
            'refund_amount.min'        => 'The refund amount must be at least 1.',
            'refund_note.required'     => 'The refund note is required.',
            'package_id.required'      => 'The plan is required.',
            'package_id.exists'        => 'The selected plan does not exist.',
            'payment_mode_id.required' => 'The payment mode is required.',
            'created_at.required'      => 'The date field is required.',
            'created_at.date_format'   => 'The date field format is incorrect.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'data'    => null,
                'errors'  => $validator->errors()->toArray(),
            ], 422)
        );
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'You are not authorized to access this resource.',
                'data'    => null,
                'errors'  => [],
            ], 403)
        );
    }
}
