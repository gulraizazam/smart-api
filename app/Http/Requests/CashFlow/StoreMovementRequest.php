<?php

declare(strict_types=1);

namespace App\Http\Requests\CashFlow;

use App\Rules\GoogleDriveUrlRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Unified create request for the /cashflow/movements surface.
 *
 * Authorisation routes to the per-kind permission slug based on the
 * source→dest combination — a user can only post a combination they could
 * have submitted on the underlying legacy screen.
 *
 * Validation defers to per-combination conditional rule blocks via
 * `Rule::when` so the SPA's single form maps onto the legacy
 * FormRequests' contracts without duplicating their entire rule trees.
 */
class StoreMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }
        // The super-slug admits everything — keep the inline check identical
        // to how the existing per-kind controllers treat it.
        if ($user->can('cashflow_manage')) {
            return true;
        }

        $sourceType = $this->input('source_type');
        $destType = $this->input('dest_type');

        if ($sourceType === 'pool' && $destType === 'pool') {
            return $user->can('cashflow_transfer_create');
        }
        if ($sourceType === 'pool' && $destType === 'staff') {
            return $user->can('cashflow_staff_advance_create');
        }
        if ($sourceType === 'staff' && $destType === 'pool') {
            return $user->can('cashflow_staff_return_create');
        }
        if ($sourceType === 'staff' && $destType === 'staff') {
            return $user->can('cashflow_staff_transfer_create');
        }

        return false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'source_id' => $this->filled('source_id') ? (int) $this->input('source_id') : null,
            'dest_id' => $this->filled('dest_id') ? (int) $this->input('dest_id') : null,
        ]);
    }

    public function rules(): array
    {
        $isPoolToPool = $this->input('source_type') === 'pool' && $this->input('dest_type') === 'pool';
        $isPoolToStaff = $this->input('source_type') === 'pool' && $this->input('dest_type') === 'staff';
        $isStaffToPool = $this->input('source_type') === 'staff' && $this->input('dest_type') === 'pool';
        $isStaffToStaff = $this->input('source_type') === 'staff' && $this->input('dest_type') === 'staff';

        // Tenant scope + soft-delete exclusion on every FK. See
        // StoreExpenseRequest for the cross-tenant + soft-delete
        // injection rationale.
        $accountId = (int) Auth::user()->account_id;
        $poolExists = Rule::exists('cash_pools', 'id')
            ->where('account_id', $accountId)
            ->whereNull('deleted_at');
        // Movement's user reference is always staff (advance/return/staff-
        // transfer). Filter out patients and deleted users.
        $userExists = Rule::exists('users', 'id')
            ->where('account_id', $accountId)
            ->whereNull('deleted_at')
            ->whereNot('user_type_id', (int) \Illuminate\Support\Facades\Config::get('constants.patient_id', 3));

        // method + reference_no were dropped from the form 2026-05-15
        // (method = noise; reference_no had 0% real-world usage). Both
        // remain accepted in the payload so legacy callers don't break.
        // Attachment is now optional too — staff workflow no longer
        // demands a receipt for every transfer.
        //
        // transfer_date is required for EVERY combo since 2026-05-15.
        // The staff-side tables grew their own date columns; the SPA
        // ships a single `transfer_date` field and MovementService
        // routes it to the right column. 7-day backdate window matches
        // pool→pool. Inline ternaries (not `Rule::when`) so the result
        // is a plain array safe to array_merge — Rule::when returns a
        // ConditionalRules object that array_merge rejects in Laravel 11+.
        $dateRule = 'required|date|before_or_equal:today|after_or_equal:' . now()->subDays(7)->toDateString();

        return array_merge(
            [
                'source_type' => 'required|in:pool,staff',
                'dest_type' => 'required|in:pool,staff',
                'source_id' => 'required|integer|min:1',
                'dest_id' => 'required|integer|min:1',
                'amount' => 'required|integer|min:1|max:99999999',
                'description' => 'nullable|string|max:500',
                'transfer_date' => $dateRule,
            ],
            $isPoolToPool ? [
                'method' => 'nullable|in:physical_cash,bank_deposit',
                'reference_no' => 'nullable|string|max:100',
                'attachment_url' => ['nullable', 'string', 'max:500', new GoogleDriveUrlRule],
                'description' => 'nullable|string|max:100',
                'source_id' => ['required', 'integer', $poolExists],
                'dest_id' => ['required', 'integer', $poolExists, 'different:source_id'],
                // New attachments (drop-zone uploads). IDs come from the
                // upload endpoint as orphan rows; the service binds them
                // to the freshly-created cash_transfer on save. Tenant +
                // orphan checks live in the exists rule so cross-tenant
                // or already-bound IDs are rejected here, not deeper in.
                'attachment_ids' => 'nullable|array|max:10',
                'attachment_ids.*' => [
                    'integer',
                    Rule::exists('cash_transfer_attachments', 'id')
                        ->where('account_id', $accountId)
                        ->whereNull('cash_transfer_id')
                        ->whereNull('deleted_at'),
                ],
            ] : [],
            $isPoolToStaff ? [
                'source_id' => ['required', 'integer', $poolExists],
                'dest_id' => ['required', 'integer', $userExists],
            ] : [],
            $isStaffToPool ? [
                'source_id' => ['required', 'integer', $userExists],
                'dest_id' => ['required', 'integer', $poolExists],
            ] : [],
            $isStaffToStaff ? [
                'source_id' => ['required', 'integer', $userExists],
                'dest_id' => ['required', 'integer', $userExists, 'different:source_id'],
            ] : [],
            // Staff-side attachments — separate table from cash_transfer_
            // attachments; IDs must be orphan (movement_id NULL) and live
            // in the caller's tenant. Empty array / missing key is fine.
            ! $isPoolToPool ? [
                'attachment_ids' => 'nullable|array|max:10',
                'attachment_ids.*' => [
                    'integer',
                    Rule::exists('movement_attachments', 'id')
                        ->where('account_id', $accountId)
                        ->whereNull('movement_id')
                        ->whereNull('deleted_at'),
                ],
            ] : [],
        );
    }

    public function messages(): array
    {
        return [
            // Used for both pool↔pool and staff↔staff combinations — generic
            // copy avoids leaking the discriminator's internal naming.
            'dest_id.different' => 'Source and destination must be different.',
        ];
    }
}
