<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ApplicationUserRequest extends FormRequest
{
    #[\Override]
    public function authorize(): bool
    {
        return true;
    }

    #[\Override]
    public function rules(): array
    {
        $userId = $this->route('user') ?? $this->route('id');

        $rules = [
            'name' => 'required|string|max:255',
            'roles' => 'required|array',
            'roles.*' => 'integer|exists:roles,id',
            'phone' => 'nullable|string|max:20',
            'gender' => 'nullable|in:1,2',
            'centers' => 'nullable|array',
            'warehouse' => 'nullable|array',
        ];

        if ($this->isMethod('post') && !$userId) {
            $rules['email'] = 'required|email|unique:users,email,NULL,id,deleted_at,NULL';
            $rules['password'] = 'required|min:8|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&#])[A-Za-z\d@$!%*?&#]{8,}$/';
        }

        if ($this->isMethod('put') || $this->isMethod('patch')) {
            $rules['phone'] = 'required|string|max:20';
            $rules['gender'] = 'required|in:1,2';
        }

        return $rules;
    }

    #[\Override]
    public function messages(): array
    {
        return [
            'name.required' => 'Name field is required',
            'email.required' => 'Email field is required',
            'email.unique' => 'Email must be unique',
            'password.required' => 'Password field is required',
            'password.min' => 'Password must be at least 8 characters',
            'password.regex' => 'Password must be a combination of numbers, upper, lower, and special characters',
            'roles.required' => 'Role is required',
            'phone.required' => 'Phone field is required',
            'gender.required' => 'Gender field is required',
        ];
    }

    #[\Override]
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => $validator->errors()->first(),
            'data' => null,
            'errors' => $validator->errors()->all(),
        ], 422));
    }
}
