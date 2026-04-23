<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateResourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && Gate::allows('resources_edit');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $accountId = (int) Auth::user()->account_id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'resource_type_id' => [
                'required',
                'integer',
                Rule::exists('resource_types', 'id')->whereNull('deleted_at'),
            ],
            'location_id' => [
                'required',
                'integer',
                Rule::exists('locations', 'id')
                    ->where(fn ($q) => $q->where('account_id', $accountId)->where('slug', 'custom'))
                    ->whereNull('deleted_at'),
            ],
            'machine_type_id' => [
                'required',
                'integer',
                Rule::exists('machine_types', 'id')->whereNull('deleted_at'),
            ],
            'active' => ['nullable', 'boolean'],
        ];
    }
}
