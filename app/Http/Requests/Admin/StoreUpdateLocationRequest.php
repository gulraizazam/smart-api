<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

class StoreUpdateLocationRequest extends AdminApiFormRequest
{
    public function rules(): array
    {
        return [
            'name'       => 'required',
            'fdo_name'   => 'required',
            'fdo_phone'  => 'required',
            'address'    => 'required',
            'google_map' => 'required',
            'city_id'    => 'required',
            'ntn'        => 'required',
            'stn'        => 'required',
        ];
    }
}
