<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

class StoreUpdateAppointmentStatusNameRequest extends AdminApiFormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required',
        ];
    }
}
