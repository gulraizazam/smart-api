<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class StoreMembershipRequest extends AdminApiFormRequest
{
    #[\Override]
    public function rules(): array
    {
        return [
            'code'               => ['required', Rule::unique('memberships', 'code')],
            'membership_type_id' => 'required|exists:membership_types,id',
        ];
    }
}
