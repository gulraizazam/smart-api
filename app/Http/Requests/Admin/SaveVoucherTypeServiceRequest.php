<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class SaveVoucherTypeServiceRequest extends FormRequest
{
    #[\Override]
    public function authorize(): bool
    {
        return true;
    }

    #[\Override]
    public function rules(): array
    {
        return [
            'voucher_id' => 'required|integer|exists:discounts,id',
            'id' => 'required|string',
        ];
    }

    #[\Override]
    public function messages(): array
    {
        return [
            'voucher_id.required' => 'Voucher type is required.',
            'id.required' => 'Location and service selection is required.',
        ];
    }

    public function locationId(): int
    {
        $parts = explode(',', $this->input('id'));

        return (int) ($parts[0] ?? 0);
    }

    public function serviceId(): int
    {
        $parts = explode(',', $this->input('id'));

        return (int) ($parts[1] ?? 0);
    }

    #[\Override]
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => $validator->errors()->first(),
            'data' => null,
            'errors' => $validator->errors()->all(),
        ], 422));
    }
}
