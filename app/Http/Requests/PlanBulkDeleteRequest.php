<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

final class PlanBulkDeleteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('plans.destroy') ?? false;
    }

    public function rules(): array
    {
        return [
            'id'   => 'required|array|min:1',
            'id.*' => 'required|integer|exists:packages,id',
        ];
    }

    public function messages(): array
    {
        return [
            'id.required' => 'At least one plan must be selected for deletion.',
            'id.array'    => 'Invalid plan selection format.',
            'id.*.exists' => 'One or more selected plans do not exist.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'data'    => null,
                'errors'  => $validator->errors()->toArray(),
            ], 422)
        );
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'You are not authorized to delete plans.',
                'data'    => null,
                'errors'  => [],
            ], 403)
        );
    }
}
