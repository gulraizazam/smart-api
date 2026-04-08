<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

class ImportMembershipsRequest extends AdminApiFormRequest
{
    #[\Override]
    public function rules(): array
    {
        return [
            'memberships_file' => ['required', 'mimes:xls,xlsx'],
        ];
    }
}
