<?php

declare(strict_types=1);
namespace App\Http\Requests\CashFlow;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class RejectExpenseRequest extends FormRequest
{
    #[\Override]
    public function authorize(): bool
    {
        return Auth::user()->can('cashflow_expense_reject');
    }

    #[\Override]
    public function rules(): array
    {
        return [
            'rejection_reason' => 'required|string|min:5|max:500',
        ];
    }

    #[\Override]
    public function messages(): array
    {
        return [
            'rejection_reason.required' => 'A reason for rejection is required.',
            'rejection_reason.min' => 'Rejection reason must be at least 5 characters.',
        ];
    }
}
