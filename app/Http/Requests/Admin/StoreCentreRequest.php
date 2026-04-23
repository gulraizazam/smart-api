<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreCentreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && Gate::allows('locations_create');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $accountId = (int) Auth::user()->account_id;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('locations', 'name')
                    ->where(fn ($q) => $q->where('account_id', $accountId))
                    ->whereNull('deleted_at'),
            ],
            'fdo_name' => ['required', 'string', 'max:255'],
            'fdo_phone' => ['required', 'string', 'max:50'],
            'address' => ['required', 'string', 'max:1000'],
            'google_map' => ['required', 'string', 'max:2000'],
            'city_id' => [
                'required',
                'integer',
                Rule::exists('cities', 'id')
                    ->where(fn ($q) => $q->where('account_id', $accountId)->where('slug', 'custom'))
                    ->whereNull('deleted_at'),
            ],
            'ntn' => ['required', 'string', 'max:100'],
            'stn' => ['required', 'string', 'max:100'],
            'tax_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'parent_id' => ['nullable', 'integer'],
            'file' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,gif,webp,svg', 'max:5120'],
            'services' => ['nullable', 'array'],
            'services.*' => ['integer'],
            'active' => ['nullable', 'boolean'],
        ];
    }
}
