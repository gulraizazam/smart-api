<?php

declare(strict_types=1);
namespace App\Http\Requests\CashFlow;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class VoidExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->can('cashflow.expense.void');
    }

    public function rules(): array
    {
        return [
            'void_reason' => 'required|string|min:10|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'void_reason.required' => 'A reason for voiding is required.',
            'void_reason.min' => 'Void reason must be at least 10 characters.',
        ];
    }
}
