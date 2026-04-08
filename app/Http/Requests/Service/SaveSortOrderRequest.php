<?php

declare(strict_types=1);

namespace App\Http\Requests\Service;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class SaveSortOrderRequest extends FormRequest
{
    #[\Override]
    public function authorize(): bool
    {
        return Gate::allows('services_sort');
    }

    /**
     * @return array<string, string>
     */
    #[\Override]
    public function rules(): array
    {
        return [
            'item_ids'   => 'required|array|min:1',
            'item_ids.*' => 'integer|exists:services,id',
        ];
    }

    /**
     * @return array<string, string>
     */
    #[\Override]
    public function messages(): array
    {
        return [
            'item_ids.required' => 'No items provided for sorting.',
            'item_ids.array'    => 'Items must be an array.',
            'item_ids.min'      => 'At least one item is required for sorting.',
        ];
    }
}
