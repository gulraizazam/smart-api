<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ToggleOperatorTestModeRequest extends FormRequest
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
            'test_mode' => ['required', 'integer', 'in:0,1'],
        ];
    }
}
