<?php

declare(strict_types=1);

namespace App\Http\Requests\Membership;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Gate;

final class CancelMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Cancel from the global module — patient-card "cancel
        // membership" has its own perm (`patients.membership.cancel`)
        // gated on the patient-card route.
        return Gate::allows('memberships.edit');
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

    public function rules(): array
    {
        return [
            'id' => ['required', 'integer', 'exists:users,id'],
        ];
    }

    public function patientId(): int
    {
        return (int) $this->input('id');
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
}
