<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

class StoreUpdateTownRequest extends AdminApiFormRequest
{
    public function rules(): array
    {
        return [
            'name'    => 'required',
            'city_id' => 'required',
        ];
    }
}
