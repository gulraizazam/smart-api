<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared request for calculateAmounts, exportExemptInvoices, downloadInvoicesZip.
 * All three methods use identical validation rules.
 */
class InvoiceCalculationRequest extends FormRequest
{
    #[\Override]
    public function authorize(): bool
    {
        return true;
    }

    #[\Override]
    public function rules(): array
    {
        return [
            'date_range'            => 'required|string',
            'location_ids'          => 'required|array',
            'location_ids.*'        => 'integer',
            'bank_taxable'          => 'required|numeric|min:0|max:100',
            'cash_percent'          => 'required|numeric|min:0|max:100',
            'consultation_amount'   => 'required|numeric|in:1500,2000',
            'tax_percent'           => 'nullable|numeric|min:0|max:100',
            'max_invoices_per_day'  => 'nullable|integer|min:1|max:10',
        ];
    }
}
