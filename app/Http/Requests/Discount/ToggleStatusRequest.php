<?php

declare(strict_types=1);

namespace App\Http\Requests\Discount;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

final class ToggleStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        // The legacy umbrella `discounts_active` covered both directions.
        // The new catalog splits it: `discounts.activate` to flip status
        // ON, `discounts.deactivate` to flip it OFF. Resolve the perm
        // from the requested status so a role can have one without the
        // other (e.g. an operator who can pause but not reactivate).
        $targetStatus = (int) $this->input('status');
        $needed = $targetStatus === 1 ? 'discounts.activate' : 'discounts.deactivate';

        return $user->can($needed);
    }

    public function rules(): array
    {
        return [
            'id'     => ['required', 'integer', 'exists:discounts,id'],
            'status' => ['required', 'in:0,1'],
        ];
    }

    public function messages(): array
    {
        return [
            'id.required'     => 'The discount ID is required.',
            'id.exists'       => 'The selected discount does not exist.',
            'status.required' => 'The status is required.',
            'status.in'       => 'The status must be 0 or 1.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'status'  => false,
                'message' => $validator->errors()->first(),
                'data'    => null,
            ], 200)
        );
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            response()->json([
                'status'  => false,
                'message' => 'You are not authorized to access this resource.',
                'data'    => null,
            ], 403)
        );
    }
}
