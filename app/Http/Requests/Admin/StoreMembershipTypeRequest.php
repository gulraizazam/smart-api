<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class StoreMembershipTypeRequest extends AdminApiFormRequest
{
    #[\Override]
    public function rules(): array
    {
        return [
            'name'   => ['required', Rule::unique('membership_types', 'name')->ignore($this->input('id'))],
            'period' => ['required', 'integer', 'min:1'],
            'amount' => ['required', 'numeric', 'min:1.00'],
        ];
    }
}
