<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\HR;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ConvertCandidateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && Gate::allows('hr_recruitment_convert');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $accountId = (int) Auth::user()->account_id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => ['required', 'string', 'min:8'],
            'user_type_id' => ['required', 'in:2,5'],
            'department_id' => [
                'nullable',
                'integer',
                Rule::exists('departments', 'id')->where(fn ($q) => $q->where('account_id', $accountId)),
            ],
            'hire_date' => ['nullable', 'date'],
            'employment_type' => ['nullable', 'in:full_time,part_time,contract'],
            'salary' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'role_id' => ['nullable', 'integer', 'exists:roles,id'],
        ];
    }
}
