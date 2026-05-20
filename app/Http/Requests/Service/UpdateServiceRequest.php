<?php

declare(strict_types=1);

namespace App\Http\Requests\Service;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class UpdateServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('services.edit');
    }

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'name'                  => 'required|string|max:255',
            'parent_id'             => 'required|integer|min:0',
            'duration'              => 'nullable|string|regex:/^\d{2}:\d{2}$/',
            'price'                 => 'nullable|numeric|min:0',
            'color'                 => 'nullable|string|max:20',
            'description'           => 'nullable|string|max:5000',
            'tax_treatment_type_id' => 'nullable|integer|exists:tax_treatment_type,id',
            'end_node'              => 'nullable|boolean',
            'complimentory'         => 'nullable|boolean',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required'    => 'Service name is required.',
            'parent_id.required' => 'Please select a parent service or choose "Parent Service".',
            'duration.regex'   => 'Duration must be in HH:MM format.',
            'price.numeric'    => 'Price must be a valid number.',
            'price.min'        => 'Price cannot be negative.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'end_node'     => $this->boolean('end_node'),
            'complimentory' => $this->boolean('complimentory'),
        ]);
    }
}
