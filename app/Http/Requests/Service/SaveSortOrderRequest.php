<?php

declare(strict_types=1);

namespace App\Http\Requests\Service;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class SaveSortOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Gated on the dedicated reorder permission so a role can have
        // services_edit (inline name/price edits) without being able to
        // change global ordering. `services_sort` is seeded by
        // PermissionSeeder (parent_id=46) — admins assign it explicitly.
        return Gate::allows('services_sort');
    }

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'parent_id'  => 'required|integer|exists:services,id',
            'item_ids'   => 'required|array|min:1',
            'item_ids.*' => 'integer|exists:services,id',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'item_ids.required' => 'No items provided for sorting.',
            'item_ids.array'    => 'Items must be an array.',
            'item_ids.min'      => 'At least one item is required for sorting.',
        ];
    }
}
