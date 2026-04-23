<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateMachineTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && Gate::allows('machineType_edit');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $accountId = (int) Auth::user()->account_id;
        $route = $this->route('machineType');
        $machineTypeId = is_object($route) ? (int) $route->id : (int) $route;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('machine_types', 'name')
                    ->ignore($machineTypeId)
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
