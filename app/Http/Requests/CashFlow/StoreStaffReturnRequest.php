<?php

declare(strict_types=1);
namespace App\Http\Requests\CashFlow;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreStaffReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->can('cashflow_staff_return_create');
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
        return [
            'user_id' => 'required|integer|exists:users,id',
            'pool_id' => 'required|integer|exists:cash_pools,id',
            'amount' => 'required|numeric|min:0.01|max:99999999.99',
            'description' => 'nullable|string|max:500',
        ];
    }
}
