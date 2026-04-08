<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SendLeadStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone'    => 'required|string',
            'status'   => 'required|string',
            'lead_id'  => 'nullable|string',
            'email'    => 'nullable|email',
            'currency' => 'nullable|string|size:3',
            'value'    => 'nullable|numeric',
        ];
    }
}
