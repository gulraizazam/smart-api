<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class UpdateMembershipTypeRequest extends AdminApiFormRequest
{
    public function rules(): array
    {
        $id = $this->route('id') ?? $this->input('id');

        return [
            'name'   => ['required', Rule::unique('membership_types', 'name')->ignore($id)],
            'period' => ['required', 'integer', 'min:1'],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
