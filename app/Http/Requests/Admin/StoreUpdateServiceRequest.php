<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

class StoreUpdateServiceRequest extends AdminApiFormRequest
{
    #[\Override]
    public function rules(): array
    {
        return [
            'name'      => 'required',
            'parent_id' => 'required',
        ];
    }
}
