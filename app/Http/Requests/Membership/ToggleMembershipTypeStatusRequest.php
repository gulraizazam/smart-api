<?php

declare(strict_types=1);

namespace App\Http\Requests\Membership;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Gate;

final class ToggleMembershipTypeStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Either side of the activate/deactivate split is enough to pass
        // the FormRequest gate; the controller then re-checks the perm
        // that actually matches the requested status.
        return Gate::any(['membership_types.activate', 'membership_types.deactivate']);
    }

    public function rules(): array
    {
        return [
            'id'     => ['required', 'integer', 'exists:membership_types,id'],
            'status' => ['required', 'in:0,1'],
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
