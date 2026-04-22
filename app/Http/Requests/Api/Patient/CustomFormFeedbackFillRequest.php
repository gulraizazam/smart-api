<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Patient;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Validation for POST /api/customformfeedbackspatient/fill.
 *
 * `form_id` identifies the CustomForms template to fill; `reference_id` is
 * the patient id (matches the web form's hidden/select inputs). Dynamic
 * custom-form field answers are allowed via the wildcard rule.
 */
class CustomFormFeedbackFillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'form_id' => ['required', 'integer', 'min:1'],
            'reference_id' => ['required', 'integer', 'min:1'],
            '*' => ['nullable'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'status' => false,
                'message' => $validator->errors()->first(),
                'data' => null,
                'errors' => $validator->errors()->toArray(),
            ], 422)
        );
    }
}
