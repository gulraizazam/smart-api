<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

class StoreUpdateMachineTypeRequest extends AdminApiFormRequest
{
    public function rules(): array
    {
        return [
            'name'     => 'required',
            'services' => 'required',
        ];
    }
}
