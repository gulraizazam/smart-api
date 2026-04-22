<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\HR;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class StoreEmployeeRequest extends FormRequest
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

        return [
            // users table
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,NULL,id,deleted_at,NULL'],
            'password' => [
                'required',
                'string',
                'min:8',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&#])[A-Za-z\d@$!%*?&#]{8,}$/',
            ],
            'phone' => ['required', 'string', 'max:50'],
            'cnic' => ['nullable', 'string', 'max:50'],
            'dob' => ['nullable', 'date'],
            'gender' => ['required', 'in:1,2'],
            'address' => ['nullable', 'string', 'max:500'],
            'user_type_id' => ['nullable', 'in:2,5'],

            // employee_details
            'department_id' => ['nullable', 'integer', "exists:departments,id,account_id,{$accountId}"],
            'designation_id' => ['nullable', 'integer', "exists:designations,id,account_id,{$accountId}"],
            'reporting_manager_id' => ['nullable', 'integer', "exists:users,id,account_id,{$accountId}"],
            'hire_date' => ['required', 'date'],
            'employment_type' => ['required', 'in:full_time,part_time,contract'],
            'shift_hours' => ['required', 'numeric', 'gt:0', 'lte:24'],
            'salary' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account_number' => ['nullable', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:255'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:50'],
            'emergency_contact_relation' => ['nullable', 'string', 'max:100'],

            // locations
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
