<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\SMSLogs;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ResendInvoiceSMSRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && Gate::allows('invoices.sms_log.view');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'id' => [
                'required',
                'integer',
                Rule::exists(SMSLogs::class, 'id'),
            ],
        ];
    }
}
