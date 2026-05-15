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

        // pool→pool inherits the transfer rules (7-day backdate window,
        // method, attachment, reference). The form's text limits drop to
        // 100 chars here to mirror StoreTransferRequest exactly. Inline
        // ternaries (not `Rule::when`) so the result is a plain array
        // safe to array_merge — Rule::when returns a ConditionalRules
        // object that array_merge rejects in Laravel 11+.
        return array_merge(
            [
                'source_type' => 'required|in:pool,staff',
                'dest_type' => 'required|in:pool,staff',
                'source_id' => 'required|integer|min:1',
                'dest_id' => 'required|integer|min:1',
                'amount' => 'required|integer|min:1|max:99999999',
                'description' => 'nullable|string|max:500',
            ],
            $isPoolToPool ? [
                'transfer_date' => 'required|date|before_or_equal:today|after_or_equal:' . now()->subDays(7)->toDateString(),
                'method' => 'required|in:physical_cash,bank_deposit',
                'reference_no' => 'nullable|string|max:100',
                'attachment_url' => ['required', 'string', 'max:500', new GoogleDriveUrlRule],
                'description' => 'nullable|string|max:100',
                'source_id' => ['required', 'integer', $poolExists],
                'dest_id' => ['required', 'integer', $poolExists, 'different:source_id'],
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
        );
    }

    public function messages(): array
    {
        return [
            // Used for both pool↔pool and staff↔staff combinations — generic
            // copy avoids leaking the discriminator's internal naming.
            'dest_id.different' => 'Source and destination must be different.',
            'attachment_url.required' => 'Transfer receipt/attachment is required.',
        ];
    }
}
