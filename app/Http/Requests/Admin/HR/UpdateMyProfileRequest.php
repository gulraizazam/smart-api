<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\HR;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * Self-service profile update — ALLOWLIST only. Employees must not be able to
 * self-promote by supplying fields like salary, department_id, manager_id,
 * hire_date, or active. Anything not listed here is silently dropped by
 * $request->validated().
 */
class UpdateMyProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'phone' => ['nullable', 'string', 'max:32'],
            'dob' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'in:1,2'],
            'address' => ['nullable', 'string', 'max:500'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:32'],
            'emergency_contact_relation' => ['nullable', 'string', 'max:64'],
        ];
    }
}
