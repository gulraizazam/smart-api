<?php

declare(strict_types=1);

namespace App\Http\Requests\Consultancy;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class FinalCalculationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'amount_type' => 'required|integer|in:0,1',
            'price' => 'nullable|numeric|min:0',
            'cash' => 'nullable|numeric|min:0',
            'balance' => 'nullable|numeric|min:0',
            'outstanding' => 'nullable|numeric|min:0',
            'settleamount' => 'nullable|numeric|min:0',
        ];
    }
}
