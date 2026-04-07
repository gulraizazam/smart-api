<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class TaxCalculationReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bank_taxable'         => 'nullable|numeric|min:0|max:100',
            'cash_taxable'         => 'nullable|numeric|min:0|max:100',
            'consultation_amount'  => 'nullable|numeric|min:0',
        ];
    }
}
