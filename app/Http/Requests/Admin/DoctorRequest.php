<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class DoctorRequest extends FormRequest
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
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'phone' => 'required|string',
            'gender' => 'required',
            'roles' => 'nullable|array',
        ];

        // For create (POST), require password and unique email
        if ($this->isMethod('POST')) {
            $rules['email'] = 'required|email|unique:users,email,NULL,id,deleted_at,NULL';
            $rules['password'] = 'required|min:8|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&#])[A-Za-z\d@$!%*?&#]{8,}$/';
        }

        // For update (PUT/PATCH), email unique except current user
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $doctorId = $this->route('doctor') ?? $this->route('id');
            $rules['email'] = 'required|email|unique:users,email,' . $doctorId;
            $rules['password'] = 'nullable';
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
            'email.email' => 'Please enter a valid email address',
            'email.unique' => 'This email is already in use',
            'phone.required' => 'Phone field is required',
            'gender.required' => 'Gender field is required',
            'password.required' => 'Password field is required',
            'password.min' => 'Password must be at least 8 characters',
            'password.regex' => 'Password must be a combination of numbers, upper, lower, and special characters',
        ];
    }
}
