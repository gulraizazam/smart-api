<?php

declare(strict_types=1);

namespace App\Http\Requests\Service;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class BundleImpactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('services.edit');
    }

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'new_price' => 'required|numeric|min:0',
        ];
    }
}
