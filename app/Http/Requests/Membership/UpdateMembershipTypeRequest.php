<?php

declare(strict_types=1);

namespace App\Http\Requests\Membership;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

final class UpdateMembershipTypeRequest extends FormRequest
{
    #[\Override]
    public function authorize(): bool
    {
        return true;
    }

    #[\Override]
    public function rules(): array
    {
        $id = $this->route('membershiptype');

        return [
            'name'         => ['required', 'string', 'max:45', Rule::unique('membership_types', 'name')->ignore($id)],
            'period'       => ['required', 'integer', 'min:1'],
            'amount'       => ['required', 'numeric', 'min:0.01'],
            'parent_id'    => ['nullable', 'integer', 'exists:membership_types,id'],
            'discount_ids' => ['nullable', 'array'],
            'discount_ids.*' => ['integer', 'exists:discounts,id'],
        ];
    }

    #[\Override]
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
