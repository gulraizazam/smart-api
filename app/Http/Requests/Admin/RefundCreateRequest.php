<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

class RefundCreateRequest extends AdminApiFormRequest
{
    public function rules(): array
    {
        return [
            'refund_amount'    => ['required', 'numeric', 'regex:/^[0-9]+$/'],
            'refund_note'      => 'required',
            'package_id'       => 'required',
            'payment_mode_id'  => 'required',
            'created_at'       => ['required', 'date', 'date_format:Y-m-d'],
        ];
    }

    public function messages(): array
    {
        return [
            'created_at.required'    => 'The created at field is required.',
            'created_at.date_format' => 'The Date field format is incorrect.',
        ];
    }
}
