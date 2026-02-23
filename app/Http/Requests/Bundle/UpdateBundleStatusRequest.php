<?php

namespace App\Http\Requests\Bundle;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateBundleStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $status = $this->input('status');
        
        if ($status == 0) {
            return Gate::allows('packages_inactive');
        }
        
        return Gate::allows('packages_active');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'id' => 'required|integer|exists:bundles,id',
            'status' => 'required|integer|in:0,1',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id.required' => 'The bundle ID is required.',
            'id.exists' => 'The selected bundle does not exist.',
            'status.required' => 'The status is required.',
            'status.in' => 'The status must be either 0 (inactive) or 1 (active).',
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
            'message' => 'You are not authorized to change bundle status.',
        ], 403));
    }
}
