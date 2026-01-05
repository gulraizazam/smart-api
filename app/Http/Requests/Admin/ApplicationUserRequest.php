<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ApplicationUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $userId = $this->route('user') ?? $this->route('id');
        
        $rules = [
            'name' => 'required|string|max:255',
            'roles' => 'required|array',
            'roles.*' => 'integer|exists:roles,id',
            'commission' => 'required|numeric|min:0|max:100',
            'phone' => 'nullable|string|max:20',
            'gender' => 'nullable|in:1,2',
            'centers' => 'nullable|array',
            'warehouse' => 'nullable|array',
        ];
        
        // For create, require email and password
        if ($this->isMethod('post') && !$userId) {
            $rules['email'] = 'required|email|unique:users,email,NULL,id,deleted_at,NULL';
            $rules['password'] = 'required|min:8|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&#])[A-Za-z\d@$!%*?&#]{8,}$/';
        }
        
        // For update, phone and gender are required
        if ($this->isMethod('put') || $this->isMethod('patch')) {
            $rules['phone'] = 'required|string|max:20';
            $rules['gender'] = 'required|in:1,2';
        }
        
        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     */
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
            'commission.required' => 'Commission is required',
            'phone.required' => 'Phone field is required',
            'gender.required' => 'Gender field is required',
        ];
    }
}
