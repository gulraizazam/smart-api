<?php

namespace App\Http\Requests;

use App\HelperModule\ApiHelper;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class PatientRequest extends FormRequest
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
            'phone' => 'required|string',
            'gender' => 'required|string',
            'email' => 'sometimes|nullable|email|max:255',
            'dob' => 'sometimes|nullable|date',
            'address' => 'sometimes|nullable|string|max:500',
            'cnic' => 'sometimes|nullable|string|max:20',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The name field is required.',
            'phone.required' => 'The phone field is required.',
            'gender.required' => 'The gender field is required.',
            'email.email' => 'Please provide a valid email address.',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator): void
    {
        $response = ApiHelper::apiResponse(
            config('constants.api_status.success'),
            $validator->errors()->first(),
            false
        );

        throw new HttpResponseException($response);
    }
}
