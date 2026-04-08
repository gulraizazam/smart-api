<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePatientDocumentRequest extends FormRequest
{
    #[\Override]
    public function authorize(): bool
    {
        return true;
    }

    #[\Override]
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
        ];
    }
}
