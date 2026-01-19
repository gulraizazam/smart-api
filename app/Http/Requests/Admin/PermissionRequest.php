<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class PermissionRequest extends FormRequest
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
            'title' => 'nullable|string|max:255',
            'parent_id' => 'nullable|integer',
            'status' => 'nullable|boolean',
        ];

        // For update, make name unique except for current record
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['name'] = 'required|string|max:255|unique:permissions,name,' . $this->route('permission');
        } else {
            $rules['name'] = 'required|string|max:255|unique:permissions,name';
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Permission name is required.',
            'name.unique' => 'This permission name already exists.',
            'name.max' => 'Permission name cannot exceed 255 characters.',
            'parent_id.exists' => 'Selected parent group does not exist.',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422)
        );
    }
}
