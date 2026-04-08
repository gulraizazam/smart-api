<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

class StoreUpdateResourceRequest extends AdminApiFormRequest
{
    public function rules(): array
    {
        return [
            'name'             => 'required',
            'resource_type_id' => 'required',
            'location_id'      => 'required',
            'machine_type_id'  => 'required',
        ];
    }
}
