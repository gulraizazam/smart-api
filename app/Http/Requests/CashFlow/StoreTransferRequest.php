<?php

declare(strict_types=1);
namespace App\Http\Requests\CashFlow;

use App\Rules\GoogleDriveUrlRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->can('cashflow.transfer.create');
    }

    public function rules(): array
    {
        // Tenant scope + soft-delete exclusion. See StoreExpenseRequest.
        $accountId = (int) Auth::user()->account_id;
        $poolAlive = Rule::exists('cash_pools', 'id')
            ->where('account_id', $accountId)
            ->whereNull('deleted_at');

        // method + reference_no were dropped from the unified create form
        // 2026-05-15 (method = noise; reference_no had 0% real-world usage);
        // both remain nullable in case a legacy caller still sends them.
        // attachment_url is REQUIRED — a cash transfer must carry its receipt /
        // bank reference for audit. A 2026-05-15 cleanup wrongly relaxed it to
        // nullable, silently dropping that control; restored 2026-06-03.
        return [
            'transfer_date' => 'required|date|before_or_equal:today|after_or_equal:' . now()->subDays(7)->toDateString(),
            'amount' => 'required|numeric|min:1|max:99999999|integer',
            'from_pool_id' => ['required', $poolAlive],
            'to_pool_id' => ['required', $poolAlive, 'different:from_pool_id'],
            'method' => 'nullable|in:physical_cash,bank_deposit',
            'reference_no' => 'nullable|string|max:100',
            'attachment_url' => ['required', 'string', 'max:500', new GoogleDriveUrlRule],
            'description' => 'nullable|string|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'to_pool_id.different' => 'Source and destination pools must be different.',
        ];
    }
}
