<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

class AllocateConfigurableDiscountRequest extends AdminApiFormRequest
{
    public function rules(): array
    {
        return [
            'discount_id' => 'required|exists:discounts,id',
            'location_id' => 'required|exists:locations,id',
        ];
    }
}
