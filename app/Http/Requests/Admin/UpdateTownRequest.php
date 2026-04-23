<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateTownRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && Gate::allows('towns_edit');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $accountId = (int) Auth::user()->account_id;
        $route = $this->route('town');
        $townId = is_object($route) ? (int) $route->id : (int) $route;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('towns', 'name')
                    ->ignore($townId)
                    ->where(fn ($q) => $q->where('account_id', $accountId)->where('city_id', $this->input('city_id')))
                    ->whereNull('deleted_at'),
            ],
            'city_id' => [
                'required',
                'integer',
                Rule::exists('cities', 'id')
                    ->where(fn ($q) => $q->where('account_id', $accountId)->where('slug', 'custom'))
                    ->whereNull('deleted_at'),
            ],
            'active' => ['nullable', 'boolean'],
        ];
    }
}
