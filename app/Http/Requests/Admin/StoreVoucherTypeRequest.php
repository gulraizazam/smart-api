<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Gate;

class StoreVoucherTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('voucher_types.create');
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'start' => 'required|date',
            'end' => 'required|date|after_or_equal:start',
            'amount' => 'nullable|numeric|min:0',
            'active' => 'nullable|boolean',
            'roles' => 'nullable|array',
            'roles.*' => 'integer|exists:roles,id',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Voucher type name is required.',
            'start.required' => 'Start date is required.',
            'end.required' => 'End date is required.',
            'end.after_or_equal' => 'End date must be on or after the start date.',
        ];
    }

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
