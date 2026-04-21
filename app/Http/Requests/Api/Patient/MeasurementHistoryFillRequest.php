<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Patient;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Validation for POST /api/measurementhistoryform/{id}.
 *
 * Mirrors the web submit behind
 * POST /admin/appointmentsmeasurement/{form_id}/{appointment_id}/submit_form.
 */
class MeasurementHistoryFillRequest extends FormRequest
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
            'service_id' => ['nullable', 'integer', 'min:1'],
            'priority' => ['nullable', 'string', 'max:50'],
            'type' => ['nullable', 'string', 'max:50'],
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
