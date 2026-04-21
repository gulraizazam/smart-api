<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Patient;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Validation for POST /api/medicalhistoryform/{id}.
 *
 * Mirrors the web submit that lives behind
 * POST /admin/appointmentsmedical/{form_id}/{appointment_id}/submit_form.
 * Dynamic custom-form field answers are allowed via the wildcard rule.
 */
class MedicalHistoryFillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reference_id' => ['required', 'integer', 'min:1'],
            'appointment_id' => ['required', 'integer', 'min:1'],
            'date' => ['nullable', 'date'],
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
