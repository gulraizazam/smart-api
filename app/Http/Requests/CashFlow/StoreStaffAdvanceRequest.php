<?php

declare(strict_types=1);
namespace App\Http\Requests\CashFlow;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreStaffAdvanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->can('cashflow_staff_advance_create');
    }

    public function rules(): array
    {
        return [
            'user_id' => 'required|exists:users,id',
            'pool_id' => 'required|exists:cash_pools,id',
            'amount' => 'required|numeric|max:99999999|integer',
            'description' => 'nullable|string|max:500',
        ];
    }
}
