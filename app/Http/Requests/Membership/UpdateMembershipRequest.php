<?php

declare(strict_types=1);

namespace App\Http\Requests\Membership;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class UpdateMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
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
        $id = $this->route('membership');

        return [
            'code'               => ['required', 'string', 'max:45', Rule::unique('memberships', 'code')->ignore($id)],
            'membership_type_id' => ['required', 'integer', 'exists:membership_types,id'],
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
}
