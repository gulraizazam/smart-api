<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateRegionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && Gate::allows('regions_edit');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $accountId = (int) Auth::user()->account_id;
        $route = $this->route('region');
        $regionId = is_object($route) ? (int) $route->id : (int) $route;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('regions', 'name')
                    ->ignore($regionId)
                    ->where(fn ($q) => $q->where('account_id', $accountId)->where('slug', 'custom'))
                    ->whereNull('deleted_at'),
            ],
            'active' => ['nullable', 'boolean'],
        ];
    }
}
