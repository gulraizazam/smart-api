<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreMachineTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && Gate::allows('machineType_create');
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
                Rule::unique('machine_types', 'name')
                    ->where(fn ($q) => $q->where('account_id', $accountId))
                    ->whereNull('deleted_at'),
            ],
            'services' => ['required', 'array', 'min:1'],
            'services.*' => [
                'integer',
                'distinct',
                Rule::exists('services', 'id')->whereNull('deleted_at'),
            ],
            'active' => ['nullable', 'boolean'],
        ];
    }
}
