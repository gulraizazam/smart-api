<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\HR;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && Gate::allows('hr_employees_manage');
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    public function rules(): array
    {
        $accountId = (int) Auth::user()->account_id;
        $userRoute = $this->route('user');
        $userId = is_object($userRoute) ? (int) $userRoute->id : (int) $userRoute;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', "unique:users,email,{$userId},id,deleted_at,NULL"],
            'password' => [
                'nullable',
                'string',
                'min:8',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&#])[A-Za-z\d@$!%*?&#]{8,}$/',
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'cnic' => ['nullable', 'string', 'max:50'],
            'dob' => ['nullable', 'date'],
            'gender' => ['nullable', 'in:1,2'],
            'address' => ['nullable', 'string', 'max:500'],

            'department_id' => ['nullable', 'integer', "exists:departments,id,account_id,{$accountId}"],
            'designation_id' => ['nullable', 'integer', "exists:designations,id,account_id,{$accountId}"],
            'reporting_manager_id' => ['nullable', 'integer', "exists:users,id,account_id,{$accountId}"],
            'hire_date' => ['nullable', 'date'],
            'employment_type' => ['nullable', 'in:full_time,part_time,contract'],
            'shift_hours' => ['nullable', 'numeric', 'gt:0', 'lte:24'],
            'salary' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account_number' => ['nullable', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:255'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:50'],
            'emergency_contact_relation' => ['nullable', 'string', 'max:100'],

            'location_ids' => ['nullable', 'array'],
            'location_ids.*' => ['integer', "exists:locations,id,account_id,{$accountId}"],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'This email is already in use.',
            'password.regex' => 'Password must be a combination of numbers, upper, lower, and special characters.',
        ];
    }
}
