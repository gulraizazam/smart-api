<?php

declare(strict_types=1);

namespace App\Http\Requests\Bundle;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Gate;

class UpdateBundleStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return match ((int) $this->input('status')) {
            0       => Gate::allows('packages.deactivate'),
            default => Gate::allows('packages.activate'),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'id'     => 'required|integer|exists:bundles,id',
            'status' => 'required|integer|in:0,1',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'id.required'     => 'The bundle ID is required.',
            'id.exists'       => 'The selected bundle does not exist.',
            'status.required' => 'The status is required.',
            'status.in'       => 'The status must be either 0 (inactive) or 1 (active).',
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'status'  => false,
            'message' => $validator->errors()->first(),
            'data'    => null,
            'errors'  => $validator->errors(),
        ], 422));
    }

    protected function failedAuthorization(): never
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'status'  => false,
            'message' => 'You are not authorized to change bundle status.',
            'data'    => null,
            'errors'  => [],
        ], 403));
    }
}
