<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RoleRequest extends FormRequest
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
        return [
            'name' => 'required|string|max:255',
            'commission' => 'nullable|numeric|min:0|max:100',
            'permission' => 'nullable|array',
            'permission.*' => 'string|exists:permissions,name',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The role name is required.',
            'name.max' => 'The role name cannot exceed 255 characters.',
            'commission.numeric' => 'Commission must be a number.',
            'commission.min' => 'Commission cannot be negative.',
            'commission.max' => 'Commission cannot exceed 100%.',
        ];
    }
}
