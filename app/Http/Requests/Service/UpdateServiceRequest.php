<?php

namespace App\Http\Requests\Service;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateServiceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('services_edit');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'parent_id' => 'required|integer|min:0',
            'duration' => 'nullable|string|regex:/^\d{2}:\d{2}$/',
            'price' => 'nullable|numeric|min:0',
            'color' => 'nullable|string|max:20',
            'description' => 'nullable|string',
            'tax_treatment_type_id' => 'nullable|integer|exists:tax_treatment_type,id',
            'end_node' => 'nullable|boolean',
            'complimentory' => 'nullable|boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Service name is required.',
            'parent_id.required' => 'Please select a parent service or choose "Parent Service".',
            'duration.regex' => 'Duration must be in HH:MM format.',
            'price.numeric' => 'Price must be a valid number.',
            'price.min' => 'Price cannot be negative.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'end_node' => $this->boolean('end_node'),
            'complimentory' => $this->boolean('complimentory'),
        ]);
    }
}
