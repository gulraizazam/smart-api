<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreCityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && Gate::allows('cities_create');
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
                Rule::unique('cities', 'name')
                    ->where(fn ($q) => $q->where('account_id', $accountId)->where('slug', 'custom'))
                    ->whereNull('deleted_at'),
            ],
            'region_id' => [
                'required',
                'integer',
                Rule::exists('regions', 'id')
                    ->where(fn ($q) => $q->where('account_id', $accountId))
                    ->whereNull('deleted_at'),
            ],
            'is_featured' => ['nullable', 'boolean'],
            'active' => ['nullable', 'boolean'],
        ];
    }
}
