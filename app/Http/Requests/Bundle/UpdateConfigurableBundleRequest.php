<?php

namespace App\Http\Requests\Bundle;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateConfigurableBundleRequest extends FormRequest
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
        $rules = [
            'name' => 'sometimes|string|max:255',
            'start' => 'sometimes|date',
            'end' => 'sometimes|date|after_or_equal:start',
            'edit_sessions_buy' => 'required|integer|min:1',
            'edit_base_service' => 'required|integer|exists:services,id',
            'edit_sessions' => 'required|array|min:1',
            'edit_services_name' => 'required|array|min:1',
            'edit_disc_type' => 'required|array|min:1',
        ];

        // Dynamic validation for sessions array
        $sessions = $this->input('edit_sessions', []);
        foreach ($sessions as $key => $value) {
            $rules["edit_sessions.{$key}"] = 'required|integer|min:1';
            $rules["edit_services_name.{$key}"] = 'required|integer|exists:services,id';
            $rules["edit_disc_type.{$key}"] = 'required|string|in:complimentory,custom';
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The bundle name is required.',
            'start.required' => 'The start date is required.',
            'end.required' => 'The end date is required.',
            'end.after_or_equal' => 'The end date must be after or equal to the start date.',
            'edit_sessions_buy.required' => 'The number of sessions to buy is required.',
            'edit_sessions_buy.min' => 'The number of sessions must be at least 1.',
            'edit_base_service.required' => 'The base service is required.',
            'edit_base_service.exists' => 'The selected base service does not exist.',
            'edit_sessions.required' => 'At least one session configuration is required.',
            'edit_services_name.required' => 'At least one service is required.',
            'edit_disc_type.required' => 'Discount type is required for each service.',
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
            'message' => 'You are not authorized to update configurable bundles.',
        ], 403));
    }
}
