<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

class SaveDiscountServicesRequest extends AdminApiFormRequest
{
    #[\Override]
    public function rules(): array
    {
        return [
            'allocation_type'   => 'required|in:Fixed,Percentage',
            'allocation_amount' => 'required|numeric|min:0',
            'location_id'       => 'required',
            'service_ids'       => 'required|array|min:1',
        ];
    }
}
