<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\HR;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && Gate::allows('hr_departments_manage');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $accountId = (int) Auth::user()->account_id;
        $route = $this->route('department');
        $departmentId = is_object($route) ? (int) $route->id : (int) $route;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('departments', 'name')
                    ->ignore($departmentId)
                    ->where(fn ($q) => $q->where('account_id', $accountId))
                    ->whereNull('deleted_at'),
            ],
            'active' => ['nullable', 'boolean'],
        ];
    }
}
