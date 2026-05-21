<?php

declare(strict_types=1);
namespace App\Http\Requests\CashFlow;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreStaffReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->can('cashflow.staff_return.create');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'user_id' => $this->filled('user_id') ? (int) $this->input('user_id') : null,
            'pool_id' => $this->filled('pool_id') ? (int) $this->input('pool_id') : null,
        ]);
    }

    public function rules(): array
    {
        $accountId = (int) Auth::user()->account_id;
        $patientTypeId = (int) \Illuminate\Support\Facades\Config::get('constants.patient_id', 3);

        return [
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')
                ->where('account_id', $accountId)
                ->whereNull('deleted_at')
                ->whereNot('user_type_id', $patientTypeId)],
            'pool_id' => ['required', 'integer', Rule::exists('cash_pools', 'id')
                ->where('account_id', $accountId)
                ->whereNull('deleted_at')],
            // Whole numbers only — mirrors advances (StoreStaffAdvanceRequest).
            // Cash returns are physical handovers and never include paisa.
            'amount' => 'required|integer|min:1|max:99999999',
            'description' => 'nullable|string|max:500',
        ];
    }
}
