<?php

declare(strict_types=1);
namespace App\Http\Requests\CashFlow;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreStaffReturnRequest extends FormRequest
{
    #[\Override]
    public function authorize(): bool
    {
        return Auth::user()->can('cashflow_staff_return_create');
    }

    #[\Override]
    public function rules(): array
    {
        return [
            'user_id' => 'required|exists:users,id',
            'pool_id' => 'required|exists:cash_pools,id',
            'amount' => 'required|numeric|min:0.01|max:99999999.99',
            'description' => 'nullable|string|max:500',
        ];
    }
}
