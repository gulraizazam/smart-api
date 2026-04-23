<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * REST PATCH contract. All fields are optional — partial updates supported.
 * `test_mode` is 0 or 1 (int); the legacy `Y/N` surface is not exposed. An
 * empty/missing `password` is dropped before the service update so existing
 * credentials are never overwritten with blanks.
 */
class UpdateOperatorSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && Gate::allows('user_operator_settings_edit');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'operator_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'url' => ['sometimes', 'nullable', 'string', 'max:500'],
            'username' => ['sometimes', 'nullable', 'string', 'max:255'],
            'password' => ['sometimes', 'nullable', 'string', 'max:255'],
            'mask' => ['sometimes', 'nullable', 'string', 'max:255'],
            'test_mode' => ['sometimes', 'required', 'integer', 'in:0,1'],
            'string_1' => ['sometimes', 'nullable', 'string', 'max:500'],
            'string_2' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
