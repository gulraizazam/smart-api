<?php

namespace App\Http\Requests\Bundle;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateBundleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('packages_edit');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'start' => 'nullable|date',
            'end' => 'nullable|date|after_or_equal:start',
            'apply_discount' => 'nullable|boolean',
            'tax_treatment_type_id' => 'nullable|integer|exists:tax_treatment_types,id',
            'service_id' => 'required|array|min:1',
            'service_id.*' => 'required|integer|exists:services,id',
            'service_price' => 'required|array|min:1',
            'service_price.*' => 'required|numeric|min:0',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The bundle name is required.',
            'price.required' => 'The bundle price is required.',
            'price.numeric' => 'The bundle price must be a number.',
            'price.min' => 'The bundle price must be at least 0.',
            'end.after_or_equal' => 'The end date must be after or equal to the start date.',
            'service_id.required' => 'At least one service is required.',
            'service_id.min' => 'At least one service is required.',
            'service_id.*.exists' => 'One or more selected services do not exist.',
            'service_price.required' => 'Service prices are required.',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'status' => false,
            'message' => $validator->errors()->first(),
            'errors' => $validator->errors(),
        ], 422));
    }

    /**
     * Handle a failed authorization attempt.
     */
    protected function failedAuthorization()
    {
        throw new HttpResponseException(response()->json([
            'status' => false,
            'message' => 'You are not authorized to update bundles.',
        ], 403));
    }
}
